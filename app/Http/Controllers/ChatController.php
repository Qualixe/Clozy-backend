<?php

namespace App\Http\Controllers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ChatController extends Controller
{
    private const MAX_TOOL_ITERATIONS = 4;

    /**
     * Storefront shopping-assistant chat. Public — no auth, matches every
     * other storefront-facing endpoint. Stateless: the client resends the
     * full message history on every turn. Never 500s on a missing/invalid
     * key or a Claude API failure — always 200 with either a reply or a
     * reason it couldn't answer, so the widget can show a friendly message.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:40'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $settings = StoreSetting::current();

        if (! $settings->anthropic_api_key) {
            return response()->json([
                'configured' => false,
                'reply' => null,
                'products' => [],
                'error' => null,
            ]);
        }

        $messages = array_map(
            fn (array $m) => ['role' => $m['role'], 'content' => $m['content']],
            $validated['messages']
        );

        try {
            $client = new Client(apiKey: $settings->anthropic_api_key);

            $foundProducts = [];
            $reply = '';

            for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
                $message = $client->messages->create(
                    model: 'claude-opus-5',
                    maxTokens: 1024,
                    outputConfig: ['effort' => 'low'],
                    system: $this->systemPrompt(),
                    tools: [$this->searchProductsToolDefinition()],
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

                        $results = $this->runSearchProducts((array) $block->input);
                        foreach ($results as $product) {
                            $foundProducts[$product['id']] = $product;
                        }

                        $toolResults[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => $block->id,
                            'content' => json_encode(array_values($results)),
                        ];
                    }
                }

                $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

                if ($message->stopReason !== 'tool_use' || empty($toolResults)) {
                    break;
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
            }

            if ($reply === '') {
                return response()->json([
                    'configured' => true,
                    'reply' => "Sorry, I couldn't come up with an answer to that. Could you try rephrasing?",
                    'products' => [],
                    'error' => null,
                ]);
            }

            return response()->json([
                'configured' => true,
                'reply' => $reply,
                'products' => array_values($foundProducts),
                'error' => null,
            ]);
        } catch (APIStatusException $e) {
            return response()->json([
                'configured' => true,
                'reply' => null,
                'products' => [],
                'error' => 'Claude API error: '.$e->getMessage(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'configured' => true,
                'reply' => null,
                'products' => [],
                'error' => 'Could not reach the Claude API: '.$e->getMessage(),
            ]);
        }
    }

    private function systemPrompt(): string
    {
        return 'You are the shopping assistant for Clozy, an online fashion and footwear store. '
            .'Help visitors find products, answer questions about sizing, materials, shipping, and store '
            .'policies, and recommend items from the store. Before recommending or describing any specific '
            .'product, price, or stock status, call the search_products tool — never invent a product, price, '
            .'or availability. If the tool returns no matches, say so and suggest a broader search rather than '
            .'making something up. Keep replies short and conversational (2-4 sentences). Prices are in BDT '
            .'(Bangladeshi Taka), shown on the site with a "৳" prefix — use that same symbol when quoting prices. '
            .'If asked about something unrelated to the store, gently redirect to how you can help with shopping.';
    }

    private function searchProductsToolDefinition(): array
    {
        return [
            'name' => 'search_products',
            'description' => 'Search the live Clozy product catalog. Call this before recommending or '
                .'describing any product so details are never invented. Returns up to `limit` matching '
                .'products with their name, price, and category.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free-text search matched against product titles, e.g. "boots" or "black hoodie". Omit to browse without a text filter.',
                    ],
                    'maxPrice' => [
                        'type' => 'number',
                        'description' => 'Optional maximum price in BDT.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max results to return. Default 5, max 8.',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    /** @return array<int, array{id: string, name: string, slug: string, price: float, image: ?string}> */
    private function runSearchProducts(array $input): array
    {
        $limit = min((int) ($input['limit'] ?? 5), 8);

        $products = Product::query()
            ->with(['images', 'variants'])
            ->when($input['query'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($input['maxPrice'] ?? null, function ($q, $maxPrice) {
                $q->where(function ($qq) use ($maxPrice) {
                    $qq->where('price', '<=', $maxPrice)
                        ->orWhereHas('variants', fn ($qv) => $qv->where('price', '<=', $maxPrice));
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $products->map(fn (Product $p) => [
            'id' => (string) $p->id,
            'name' => $p->title,
            'slug' => $p->slug,
            'price' => $p->has_variants
                ? (float) ($p->variants->min('price') ?? 0)
                : (float) ($p->price ?? 0),
            'image' => $p->images->first()?->url,
        ])->values()->all();
    }
}
