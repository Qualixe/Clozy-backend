<?php

namespace App\Http\Controllers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Policy;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SmsLog;
use App\Models\StoreSetting;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\StoreAnalyticsService;
use App\Support\AiErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
        $key = match ($settings->ai_provider) {
            'openai' => $settings->openai_api_key,
            'gemini' => $settings->gemini_api_key,
            default => $settings->anthropic_api_key,
        };

        if (! $key) {
            return response()->json(['configured' => false, 'reply' => null, 'error' => null]);
        }

        $tools = $this->availableTools($user);
        if (empty($tools)) {
            return response()->json([
                'configured' => true,
                'reply' => "You don't have permission to view any of the store data I can help with — ask an admin to grant you at least one of: View Orders, View Products, View Analytics, View Discounts, View Reviews, View CMS Pages, View Staff, Manage Settings, View Menus, View Categories, View Media, or View SMS.",
                'error' => null,
            ]);
        }

        $messages = array_map(
            fn (array $m) => ['role' => $m['role'], 'content' => $m['content']],
            $validated['messages']
        );

        return match ($settings->ai_provider) {
            'openai' => $this->sendViaOpenAiCompatible(
                'https://api.openai.com/v1/chat/completions',
                $settings->openai_api_key,
                $settings->openai_model ?: 'gpt-4o-mini',
                'OpenAI',
                $messages,
                $tools,
            ),
            'gemini' => $this->sendViaOpenAiCompatible(
                'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
                $settings->gemini_api_key,
                $settings->gemini_model ?: 'gemini-flash-latest',
                'Gemini',
                $messages,
                $tools,
            ),
            default => $this->sendViaAnthropic($settings, $messages, $tools),
        };
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
                    // array_values() — $tools is keyed by tool name (see
                    // availableTools()/runTool()), and array_map() over an
                    // associative array preserves those string keys, which
                    // json_encode then serializes as a JSON *object* instead
                    // of an array. Reindexed here so the API always gets a
                    // real array.
                    tools: array_values(array_map(fn (array $t) => $t['anthropic'], $tools)),
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
            return response()->json(['configured' => true, 'reply' => null, 'error' => AiErrorMessage::forStatus($e->status ?? 500, 'Anthropic')]);
        } catch (Throwable $e) {
            return response()->json(['configured' => true, 'reply' => null, 'error' => AiErrorMessage::forConnectionFailure('Anthropic')]);
        }
    }

    /**
     * Shared by OpenAI and Gemini — Gemini exposes an OpenAI-compatible
     * endpoint (`/v1beta/openai/...`) that accepts the exact same tool-call
     * request/response shape, so tool definitions only ever need an
     * 'anthropic' and an 'openai' shape (see the *Tool() methods below),
     * not a third Gemini-specific one.
     */
    private function sendViaOpenAiCompatible(
        string $baseUrl,
        ?string $apiKey,
        string $model,
        string $providerLabel,
        array $messages,
        array $tools,
    ): JsonResponse {
        $openAiMessages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ...$messages,
        ];

        try {
            $reply = '';

            for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
                $response = Http::withToken($apiKey)
                    ->timeout(30)
                    ->post($baseUrl, [
                        'model' => $model,
                        'max_tokens' => 1024,
                        'messages' => $openAiMessages,
                        // array_values() — see the matching comment in
                        // sendViaAnthropic(); same associative-array-vs-
                        // JSON-array pitfall applies here too.
                        'tools' => array_values(array_map(fn (array $t) => $t['openai'], $tools)),
                    ]);

                if (! $response->successful()) {
                    return response()->json([
                        'configured' => true,
                        'reply' => null,
                        'error' => AiErrorMessage::forStatus($response->status(), $providerLabel),
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
            return response()->json(['configured' => true, 'reply' => null, 'error' => AiErrorMessage::forConnectionFailure($providerLabel)]);
        }
    }

    private function systemPrompt(): string
    {
        return 'You are a store-operations copilot for Clozy admins, inside their dashboard. Answer questions '
            .'about orders, customers, products, discounts, reviews, CMS content (policies/FAQs), navigation menus, '
            .'categories, the media library, SMS logs, staff accounts, subscribers, and store performance using '
            .'the tools available to you — never invent a number, order, product, menu item, or any other fact '
            .'that a tool didn\'t return. If a tool returns nothing matching, say so '
            .'plainly rather than guessing. The specific tools you have access to depend on the asking admin\'s '
            .'own dashboard permissions, so if you don\'t have a tool for what\'s asked, say that plainly (e.g. '
            .'"I don\'t have access to staff accounts") rather than trying to answer from general knowledge or a '
            .'different tool. Never reveal or guess at credentials, API keys, passwords, or payment gateway '
            .'secrets — you have no tool that returns those, and none of the tools you\'ll ever be given will. '
            .'Amounts are in BDT (Bangladeshi Taka), shown with a "৳" prefix — use that symbol when quoting '
            .'figures. Keep replies concise and direct; use short bullet points for lists.';
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

        // Gated on the exact same permission each corresponding dashboard
        // page requires (see lib/sidebar-items.ts on the frontend) — the
        // copilot can never see a category of data the asking user
        // couldn't already reach through the normal dashboard nav.
        if ($user->can('view_orders')) {
            $tools['search_orders'] = $this->ordersTool();
            // Customers has no backend resource of its own — same as the
            // dashboard's Customers page, it's derived from Orders.
            $tools['search_customers'] = $this->customersTool();
        }
        if ($user->can('view_products')) {
            $tools['search_products'] = $this->productsTool();
        }
        if ($user->can('view_analytics')) {
            $tools['get_analytics_summary'] = $this->analyticsTool();
        }
        if ($user->can('view_discounts')) {
            $tools['search_discounts'] = $this->discountsTool();
        }
        if ($user->can('view_reviews')) {
            $tools['search_reviews'] = $this->reviewsTool();
        }
        if ($user->can('view_cms_pages')) {
            $tools['search_policies'] = $this->policiesTool();
            $tools['search_faqs'] = $this->faqsTool();
        }
        if ($user->can('view_staff')) {
            $tools['search_staff'] = $this->staffTool();
        }
        if ($user->can('manage_settings')) {
            $tools['search_subscribers'] = $this->subscribersTool();
        }
        if ($user->can('view_menus')) {
            $tools['search_menus'] = $this->menusTool();
        }
        if ($user->can('view_categories')) {
            $tools['search_categories'] = $this->categoriesTool();
        }
        if ($user->can('view_media')) {
            $tools['search_media'] = $this->mediaTool();
        }
        if ($user->can('view_sms')) {
            $tools['search_sms_logs'] = $this->smsLogsTool();
        }

        return $tools;
    }

    /**
     * Both an Anthropic- and OpenAI-shaped tool definition built from one
     * name/description/schema, alongside the closure that actually runs
     * it — avoids repeating the two near-identical shapes for every tool.
     */
    private function buildTool(string $name, string $description, array $schema, callable $run): array
    {
        return [
            'anthropic' => [
                'name' => $name,
                'description' => $description,
                'input_schema' => $schema,
            ],
            'openai' => [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $description,
                    'parameters' => $schema,
                ],
            ],
            'run' => $run,
        ];
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
        return $this->buildTool(
            'search_orders',
            'Search and filter the store\'s real orders by status and/or customer. '
                .'Returns order number, customer, status, payment method, total, and date.',
            [
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
            ],
            function (array $input) {
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
        );
    }

    private function customersTool(): array
    {
        return $this->buildTool(
            'search_customers',
            'Search the store\'s customers, derived from their order history (there\'s no separate '
                .'customer record — same as the dashboard\'s Customers page). Returns name, email, phone, '
                .'how many orders they\'ve placed, and total spent.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against name, email, or phone.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max results to return. Default 8, max 15.',
                    ],
                ],
                'required' => [],
            ],
            function (array $input) {
                $limit = min((int) ($input['limit'] ?? 8), 15);

                $orders = Order::query()
                    ->when($input['query'] ?? null, function ($q, $query) {
                        $q->where(function ($qq) use ($query) {
                            $qq->where('customer_name', 'like', "%{$query}%")
                                ->orWhere('customer_email', 'like', "%{$query}%")
                                ->orWhere('customer_phone', 'like', "%{$query}%");
                        });
                    })
                    ->orderByDesc('created_at')
                    ->get();

                // Grouped by email — walk-in/POS orders with no email are
                // each their own customer, same logic as the dashboard's
                // Customers page (see customers-table.tsx / toCustomers()).
                $customers = [];
                foreach ($orders as $order) {
                    $key = $order->customer_email ?: 'walkin-'.$order->id;
                    $customers[$key] ??= [
                        'name' => $order->customer_name,
                        'email' => $order->customer_email,
                        'phone' => $order->customer_phone,
                        'orders' => 0,
                        'totalSpent' => 0.0,
                    ];
                    $customers[$key]['orders']++;
                    $customers[$key]['totalSpent'] += (float) $order->total;
                }

                return array_slice(array_values($customers), 0, $limit);
            },
        );
    }

    private function productsTool(): array
    {
        return $this->buildTool(
            'search_products',
            'Search the real product catalog, including current stock levels. '
                .'Returns name, price, stock, and category.',
            [
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
            ],
            function (array $input) {
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
        );
    }

    private function analyticsTool(): array
    {
        return $this->buildTool(
            'get_analytics_summary',
            'Get aggregated store performance for the trailing N days: total orders, '
                .'revenue, average order value, orders by status, daily revenue, and top products by revenue.',
            [
                'type' => 'object',
                'properties' => [
                    'periodDays' => [
                        'type' => 'integer',
                        'description' => 'How many trailing days to summarize. Default 90.',
                    ],
                ],
                'required' => [],
            ],
            fn (array $input) => $this->analytics->summary(min((int) ($input['periodDays'] ?? 90), 365)),
        );
    }

    private function discountsTool(): array
    {
        return $this->buildTool(
            'search_discounts',
            'Search the store\'s discount codes. Returns code, type, value, usage count vs. limit, '
                .'active status, and expiry.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against the discount code.',
                    ],
                    'activeOnly' => [
                        'type' => 'boolean',
                        'description' => 'When true, only return codes currently marked active.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max results to return. Default 8, max 15.',
                    ],
                ],
                'required' => [],
            ],
            function (array $input) {
                $limit = min((int) ($input['limit'] ?? 8), 15);

                $discounts = Discount::query()
                    ->when($input['query'] ?? null, fn ($q, $query) => $q->where('code', 'like', "%{$query}%"))
                    ->when($input['activeOnly'] ?? false, fn ($q) => $q->where('active', true))
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get();

                return $discounts->map(fn (Discount $d) => [
                    'code' => $d->code,
                    'type' => $d->type,
                    'value' => $d->value !== null ? (float) $d->value : null,
                    'buyQty' => $d->buy_qty,
                    'getQty' => $d->get_qty,
                    'usedCount' => $d->used_count,
                    'usageLimit' => $d->usage_limit,
                    'active' => (bool) $d->active,
                    'endsAt' => $d->ends_at?->format('Y-m-d'),
                ])->values()->all();
            },
        );
    }

    private function reviewsTool(): array
    {
        return $this->buildTool(
            'search_reviews',
            'Search product reviews. Returns product name, author, rating, moderation status, '
                .'and a snippet of the review body.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against the product name or reviewer name.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['pending', 'approved', 'rejected'],
                        'description' => 'Filter by moderation status. Omit to include every status.',
                    ],
                    'maxRating' => [
                        'type' => 'integer',
                        'description' => 'Only return reviews at or below this rating (1-5) — useful for finding complaints.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max results to return. Default 8, max 15.',
                    ],
                ],
                'required' => [],
            ],
            function (array $input) {
                $limit = min((int) ($input['limit'] ?? 8), 15);

                $reviews = ProductReview::query()
                    ->with('product')
                    ->when($input['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                    ->when($input['maxRating'] ?? null, fn ($q, $rating) => $q->where('rating', '<=', $rating))
                    ->when($input['query'] ?? null, function ($q, $query) {
                        $q->where(function ($qq) use ($query) {
                            $qq->where('author', 'like', "%{$query}%")
                                ->orWhereHas('product', fn ($qp) => $qp->where('title', 'like', "%{$query}%"));
                        });
                    })
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get();

                return $reviews->map(fn (ProductReview $r) => [
                    'product' => $r->product?->title,
                    'author' => $r->author,
                    'rating' => $r->rating,
                    'status' => $r->status,
                    'body' => Str::limit($r->body, 200),
                ])->values()->all();
            },
        );
    }

    private function policiesTool(): array
    {
        return $this->buildTool(
            'search_policies',
            'List the store\'s CMS policy pages (Privacy Policy, Shipping Policy, etc.). '
                .'Returns title, slug, and publish status.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against the policy title.',
                    ],
                ],
                'required' => [],
            ],
            fn (array $input) => Policy::query()
                ->when($input['query'] ?? null, fn ($q, $query) => $q->where('title', 'like', "%{$query}%"))
                ->orderBy('title')
                ->get()
                ->map(fn (Policy $p) => [
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'status' => $p->status,
                ])->values()->all(),
        );
    }

    private function faqsTool(): array
    {
        return $this->buildTool(
            'search_faqs',
            'List the store\'s published/draft FAQ entries. Returns the question, answer, and status.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against the question.',
                    ],
                ],
                'required' => [],
            ],
            fn (array $input) => Faq::query()
                ->when($input['query'] ?? null, fn ($q, $query) => $q->where('question', 'like', "%{$query}%"))
                ->orderBy('position')
                ->get()
                ->map(fn (Faq $f) => [
                    'question' => $f->question,
                    'answer' => $f->answer,
                    'status' => $f->status,
                ])->values()->all(),
        );
    }

    private function staffTool(): array
    {
        return $this->buildTool(
            'search_staff',
            'Search dashboard staff/team accounts. Returns name, email, and role only — '
                .'never passwords or any credential.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against name or email.',
                    ],
                    'role' => [
                        'type' => 'string',
                        'enum' => ['owner', 'admin', 'staff'],
                        'description' => 'Filter by role. Omit to include every role.',
                    ],
                ],
                'required' => [],
            ],
            fn (array $input) => User::query()
                ->whereIn('role', ['owner', 'admin', 'staff'])
                ->when($input['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
                ->when($input['query'] ?? null, function ($q, $query) {
                    $q->where(function ($qq) use ($query) {
                        $qq->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
                    });
                })
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                ])->values()->all(),
        );
    }

    private function subscribersTool(): array
    {
        return $this->buildTool(
            'search_subscribers',
            'Search the newsletter/marketing email subscriber list. Returns email and signup date.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against the email address.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max results to return. Default 10, max 25.',
                    ],
                ],
                'required' => [],
            ],
            function (array $input) {
                $limit = min((int) ($input['limit'] ?? 10), 25);

                return Subscriber::query()
                    ->when($input['query'] ?? null, fn ($q, $query) => $q->where('email', 'like', "%{$query}%"))
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Subscriber $s) => [
                        'email' => $s->email,
                        'subscribedAt' => $s->created_at?->format('Y-m-d'),
                    ])->values()->all();
            },
        );
    }

    private function menusTool(): array
    {
        return $this->buildTool(
            'search_menus',
            'List the store\'s navigation menus (e.g. "Main Menu", "Footer Menu") and their items — '
                .'label, URL, and nested sub-items (megamenu columns/links), up to 3 levels deep.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against the menu\'s name or handle. Omit to list every menu.',
                    ],
                ],
                'required' => [],
            ],
            fn (array $input) => Menu::query()
                ->with('items.children.children')
                ->when($input['query'] ?? null, function ($q, $query) {
                    $q->where(function ($qq) use ($query) {
                        $qq->where('name', 'like', "%{$query}%")
                            ->orWhere('handle', 'like', "%{$query}%");
                    });
                })
                ->orderBy('name')
                ->get()
                ->map(fn (Menu $m) => [
                    'name' => $m->name,
                    'handle' => $m->handle,
                    'items' => $m->items->map(fn (MenuItem $item) => $this->flattenMenuItem($item))->values()->all(),
                ])->values()->all(),
        );
    }

    /** @return array{label: string, url: string, children: array} */
    private function flattenMenuItem(MenuItem $item): array
    {
        return [
            'label' => $item->label,
            'url' => $item->url,
            'children' => $item->children->map(fn (MenuItem $child) => $this->flattenMenuItem($child))->values()->all(),
        ];
    }

    private function categoriesTool(): array
    {
        return $this->buildTool(
            'search_categories',
            'Search the store\'s product categories/collections. Returns name, slug, position, and how '
                .'many products are primarily assigned to it.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against the category name.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max results to return. Default 15, max 30.',
                    ],
                ],
                'required' => [],
            ],
            function (array $input) {
                $limit = min((int) ($input['limit'] ?? 15), 30);

                return Category::query()
                    ->withCount('products')
                    ->when($input['query'] ?? null, fn ($q, $query) => $q->where('name', 'like', "%{$query}%"))
                    ->orderBy('position')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Category $c) => [
                        'name' => $c->name,
                        'slug' => $c->slug,
                        'productCount' => $c->products_count,
                    ])->values()->all();
            },
        );
    }

    private function mediaTool(): array
    {
        return $this->buildTool(
            'search_media',
            'Search the uploaded media library (images used across products, CMS, and theme sections). '
                .'Returns filename, size, and MIME type.',
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text match against the filename.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max results to return. Default 10, max 25.',
                    ],
                ],
                'required' => [],
            ],
            function (array $input) {
                $limit = min((int) ($input['limit'] ?? 10), 25);

                return Media::query()
                    ->when($input['query'] ?? null, fn ($q, $query) => $q->where('filename', 'like', "%{$query}%"))
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Media $m) => [
                        'filename' => $m->filename,
                        'mimeType' => $m->mime_type,
                        'sizeKb' => $m->size ? round($m->size / 1024, 1) : null,
                    ])->values()->all();
            },
        );
    }

    private function smsLogsTool(): array
    {
        return $this->buildTool(
            'search_sms_logs',
            'Search the SMS notification log (order confirmations, cancellations, promotional sends). '
                .'Returns recipient, type, delivery status, and a snippet of the message.',
            [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'description' => 'Filter by delivery status (e.g. "sent", "failed") — exact values depend on the SMS gateway.',
                    ],
                    'type' => [
                        'type' => 'string',
                        'description' => 'Filter by message type, e.g. "order_confirmation", "order_cancelled", "promotional".',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max results to return. Default 10, max 25.',
                    ],
                ],
                'required' => [],
            ],
            function (array $input) {
                $limit = min((int) ($input['limit'] ?? 10), 25);

                return SmsLog::query()
                    ->when($input['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                    ->when($input['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn (SmsLog $s) => [
                        'recipient' => $s->recipient,
                        'type' => $s->type,
                        'status' => $s->status,
                        'message' => Str::limit($s->message, 160),
                        'sentAt' => $s->created_at?->format('Y-m-d H:i'),
                    ])->values()->all();
            },
        );
    }
}
