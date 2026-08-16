<?php

namespace App\Http\Controllers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\StoreAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Dashboard "store ops copilot" — an admin asks about orders/products/
 * analytics in plain language. Separate from ChatController (the public
 * storefront shopping assistant): this one is auth-gated, and which tools
 * it's even allowed to use depends on the caller's own granted permissions
 * (see availableTools()), so it can never surface data a staff member
 * couldn't already see through the normal dashboard.
 */
class AdminChatController extends Controller
{
    private const MAX_TOOL_ITERATIONS = 4;

    public function __construct(private readonly StoreAnalyticsService $analytics) {}

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:40'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        if (! $user->canAccessDashboard()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $settings = StoreSetting::current();
        $useOpenAi = $settings->ai_provider === 'openai';
        $key = $useOpenAi ? $settings->openai_api_key : $settings->anthropic_api_key;

        if (! $key) {
            return response()->json(['configured' => false, 'reply' => null, 'error' => null]);
        }

        $tools = $this->availableTools($user);
        if (empty($tools)) {
            return response()->json([
                'configured' => true,
                'reply' => "You don't have permission to view any of the store data I can help with — ask an admin to grant you View Orders, View Products, or View Analytics.",
                'error' => null,
            ]);
        }

        $messages = array_map(
            fn (array $m) => ['role' => $m['role'], 'content' => $m['content']],
            $validated['messages']
        );

        return $useOpenAi
            ? $this->sendViaOpenAi($settings, $messages, $tools)
            : $this->sendViaAnthropic($settings, $messages, $tools);
    }

    private function sendViaAnthropic(StoreSetting $settings, array $messages, array $tools): JsonResponse
    {
        try {
            $client = new Client(apiKey: $settings->anthropic_api_key);
            $reply = '';

            for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
                $message = $client->messages->create(
                    model: 'claude-opus-5',
                    maxTokens: 1024,
                    outputConfig: ['effort' => 'low'],
                    system: $this->systemPrompt(),
                    tools: array_map(fn (array $t) => $t['anthropic'], $tools),
                    messages: $messages,
                );

                $assistantContent = [];
                $toolResults = [];

                foreach ($message->content as $block) {
                    if ($block->type === 'text') {
                        $reply .= $block->text;
                        $assistantContent[] = ['type' => 'text', 'text' => $block->text];
                    } elseif ($block->type === 'tool_use') {
                        $assistantContent[] = [
                            'type' => 'tool_use',
                            'id' => $block->id,
                            'name' => $block->name,
                            'input' => $block->input,
                        ];

                        $result = $this->runTool($tools, $block->name, (array) $block->input);

                        $toolResults[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => $block->id,
                            'content' => json_encode($result),
                        ];
                    }
                }

                $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

                if ($message->stopReason !== 'tool_use' || empty($toolResults)) {
                    break;
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
            }

            return response()->json([
                'configured' => true,
                'reply' => $reply !== '' ? $reply : "Sorry, I couldn't come up with an answer to that. Could you try rephrasing?",
                'error' => null,
            ]);
        } catch (APIStatusException $e) {
            return response()->json(['configured' => true, 'reply' => null, 'error' => 'Claude API error: '.$e->getMessage()]);
        } catch (Throwable $e) {
            return response()->json(['configured' => true, 'reply' => null, 'error' => 'Could not reach the Claude API: '.$e->getMessage()]);
        }
    }

    private function sendViaOpenAi(StoreSetting $settings, array $messages, array $tools): JsonResponse
    {
        $openAiMessages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ...$messages,
        ];

        try {
            $reply = '';

            for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
                $response = Http::withToken($settings->openai_api_key)
                    ->timeout(30)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $settings->openai_model ?: 'gpt-4o-mini',
                        'max_tokens' => 1024,
                        'messages' => $openAiMessages,
                        'tools' => array_map(fn (array $t) => $t['openai'], $tools),
                    ]);

                if (! $response->successful()) {
                    return response()->json([
                        'configured' => true,
                        'reply' => null,
                        'error' => 'OpenAI API error: '.($response->json('error.message') ?? $response->body()),
                    ]);
                }

                $message = $response->json('choices.0.message') ?? [];
                $toolCalls = $message['tool_calls'] ?? [];

                if (! empty($message['content'])) {
                    $reply .= $message['content'];
                }

                $openAiMessages[] = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? null,
                    ...($toolCalls ? ['tool_calls' => $toolCalls] : []),
                ];

                if (empty($toolCalls)) {
                    break;
                }

                foreach ($toolCalls as $call) {
                    $input = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
                    $result = $this->runTool($tools, $call['function']['name'], $input);

                    $openAiMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'],
                        'content' => json_encode($result),
                    ];
                }
            }

            return response()->json([
                'configured' => true,
                'reply' => $reply !== '' ? $reply : "Sorry, I couldn't come up with an answer to that. Could you try rephrasing?",
                'error' => null,
            ]);
        } catch (Throwable $e) {
            return response()->json(['configured' => true, 'reply' => null, 'error' => 'Could not reach the OpenAI API: '.$e->getMessage()]);
        }
    }

    private function systemPrompt(): string
    {
        return 'You are a store-operations copilot for Clozy admins, inside their dashboard. Answer questions '
            .'about orders, products, and store performance using the tools available to you — never invent a '
            .'number, order, or product that a tool didn\'t return. If a tool returns nothing matching, say so '
            .'plainly rather than guessing. If you don\'t have a tool for what\'s asked (e.g. you weren\'t given '
            .'access to orders), say that plainly too, and don\'t try to answer from general knowledge. Amounts '
            .'are in BDT (Bangladeshi Taka), shown with a "৳" prefix — use that symbol when quoting figures. Keep '
            .'replies concise and direct; use short bullet points for lists of orders/products.';
    }

    /**
     * Both an Anthropic- and OpenAI-shaped tool definition side by side
     * with the closure that actually runs it, keyed by name — this is
     * what runTool() dispatches on, and what limits which data a given
     * user's copilot session can even ask for. Reads for a permission the
     * user lacks are excluded from the array entirely, not just hidden.
     *
     * @return array<string, array{anthropic: array, openai: array, run: callable}>
     */
    private function availableTools(User $user): array
    {
        $tools = [];

        if ($user->can('view_orders')) {
            $tools['search_orders'] = $this->ordersTool();
        }
        if ($user->can('view_products')) {
            $tools['search_products'] = $this->productsTool();
        }
        if ($user->can('view_analytics')) {
            $tools['get_analytics_summary'] = $this->analyticsTool();
        }

        return $tools;
    }

    private function runTool(array $tools, string $name, array $input): array
    {
        if (! isset($tools[$name])) {
            return ['error' => "Tool \"{$name}\" is not available."];
        }

        return ($tools[$name]['run'])($input);
    }

    private function ordersTool(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['processing', 'fulfilled', 'cancelled'],
                    'description' => 'Filter by order status. Omit to include every status.',
                ],
                'customerQuery' => [
                    'type' => 'string',
                    'description' => 'Free-text match against the customer\'s name, email, or phone.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max results to return. Default 8, max 15.',
                ],
            ],
            'required' => [],
        ];

        return [
            'anthropic' => [
                'name' => 'search_orders',
                'description' => 'Search and filter the store\'s real orders by status and/or customer. '
                    .'Returns order number, customer, status, payment method, total, and date.',
                'input_schema' => $schema,
            ],
            'openai' => [
                'type' => 'function',
                'function' => [
                    'name' => 'search_orders',
                    'description' => 'Search and filter the store\'s real orders by status and/or customer. '
                        .'Returns order number, customer, status, payment method, total, and date.',
                    'parameters' => $schema,
                ],
            ],
            'run' => function (array $input) {
                $limit = min((int) ($input['limit'] ?? 8), 15);

                $orders = Order::query()
                    ->when($input['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                    ->when($input['customerQuery'] ?? null, function ($q, $query) {
                        $q->where(function ($qq) use ($query) {
                            $qq->where('customer_name', 'like', "%{$query}%")
                                ->orWhere('customer_email', 'like', "%{$query}%")
                                ->orWhere('customer_phone', 'like', "%{$query}%");
                        });
                    })
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get();

                return $orders->map(fn (Order $o) => [
                    'orderNumber' => '#'.$o->order_number,
                    'customer' => $o->customer_name,
                    'status' => $o->status,
                    'payment' => $o->payment_method,
                    'total' => (float) $o->total,
                    'date' => $o->created_at->format('Y-m-d'),
                ])->values()->all();
            },
        ];
    }

    private function productsTool(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Free-text search matched against product titles.',
                ],
                'lowStockOnly' => [
                    'type' => 'boolean',
                    'description' => 'When true, only return products with fewer than 5 in stock (checks the lowest variant stock for variant products).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max results to return. Default 8, max 15.',
                ],
            ],
            'required' => [],
        ];

        return [
            'anthropic' => [
                'name' => 'search_products',
                'description' => 'Search the real product catalog, including current stock levels. '
                    .'Returns name, price, stock, and category.',
                'input_schema' => $schema,
            ],
            'openai' => [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search the real product catalog, including current stock levels. '
                        .'Returns name, price, stock, and category.',
                    'parameters' => $schema,
                ],
            ],
            'run' => function (array $input) {
                $limit = min((int) ($input['limit'] ?? 8), 15);

                $products = Product::query()
                    ->with(['category', 'variants'])
                    ->when($input['query'] ?? null, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Product $p) => [
                        'name' => $p->title,
                        'category' => $p->category?->name,
                        'price' => $p->has_variants ? (float) ($p->variants->min('price') ?? 0) : (float) ($p->price ?? 0),
                        'stock' => $p->has_variants ? (int) ($p->variants->min('stock') ?? 0) : (int) ($p->stock ?? 0),
                    ]);

                if ($input['lowStockOnly'] ?? false) {
                    $products = $products->filter(fn (array $p) => $p['stock'] < 5);
                }

                return $products->take($limit)->values()->all();
            },
        ];
    }

    private function analyticsTool(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'periodDays' => [
                    'type' => 'integer',
                    'description' => 'How many trailing days to summarize. Default 90.',
                ],
            ],
            'required' => [],
        ];

        return [
            'anthropic' => [
                'name' => 'get_analytics_summary',
                'description' => 'Get aggregated store performance for the trailing N days: total orders, '
                    .'revenue, average order value, orders by status, daily revenue, and top products by revenue.',
                'input_schema' => $schema,
            ],
            'openai' => [
                'type' => 'function',
                'function' => [
                    'name' => 'get_analytics_summary',
                    'description' => 'Get aggregated store performance for the trailing N days: total orders, '
                        .'revenue, average order value, orders by status, daily revenue, and top products by revenue.',
                    'parameters' => $schema,
                ],
            ],
            'run' => fn (array $input) => $this->analytics->summary(min((int) ($input['periodDays'] ?? 90), 365)),
        ];
    }
}
