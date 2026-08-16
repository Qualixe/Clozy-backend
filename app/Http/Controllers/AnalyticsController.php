<?php

namespace App\Http\Controllers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\StoreSetting;
use App\Services\StoreAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class AnalyticsController extends Controller
{
    public function __construct(private readonly StoreAnalyticsService $analytics) {}

    private const SYSTEM_PROMPT = 'You are an e-commerce analytics assistant for Clozy, an online fashion and footwear store. '
        .'You will be given aggregated order data as JSON, covering the last 90 days. Write a concise, '
        .'plain-language summary for the store owner: 2-4 short paragraphs or bullet points covering '
        .'overall performance, the most notable trend(s), and 1-3 concrete, specific suggestions. '
        .'Only use numbers present in the data — never invent figures. Amounts are in BDT (Bangladeshi '
        .'Taka). Do not use markdown headings; plain sentences and simple "- " bullets only. '
        .'Keep the whole response under 200 words.';

    /**
     * On-demand AI-written summary of recent store performance. Never
     * throws on a missing/invalid key or an API failure — always returns
     * 200 with either an insight or a reason it couldn't be generated, so
     * the dashboard can render a friendly message instead of a broken
     * page. Uses whichever provider is selected in Settings > AI.
     */
    public function aiInsights(): JsonResponse
    {
        $settings = StoreSetting::current();
        $useOpenAi = $settings->ai_provider === 'openai';
        $key = $useOpenAi ? $settings->openai_api_key : $settings->anthropic_api_key;

        if (! $key) {
            return response()->json([
                'configured' => false,
                'insight' => null,
                'error' => null,
            ]);
        }

        $stats = $this->analytics->summary(90);

        return $useOpenAi
            ? $this->openAiInsight($settings, $stats)
            : $this->anthropicInsight($settings, $stats);
    }

    private function anthropicInsight(StoreSetting $settings, array $stats): JsonResponse
    {
        try {
            $client = new Client(apiKey: $settings->anthropic_api_key);

            $message = $client->messages->create(
                model: 'claude-opus-5',
                maxTokens: 1024,
                outputConfig: ['effort' => 'medium'],
                system: self::SYSTEM_PROMPT,
                messages: [
                    ['role' => 'user', 'content' => json_encode($stats, JSON_PRETTY_PRINT)],
                ],
            );

            $text = '';
            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $text .= $block->text;
                }
            }

            if ($message->stopReason === 'refusal' || $text === '') {
                return response()->json([
                    'configured' => true,
                    'insight' => null,
                    'error' => 'Claude declined to generate a summary for this data.',
                ]);
            }

            return response()->json([
                'configured' => true,
                'insight' => $text,
                'error' => null,
            ]);
        } catch (APIStatusException $e) {
            return response()->json([
                'configured' => true,
                'insight' => null,
                'error' => 'Claude API error: '.$e->getMessage(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'configured' => true,
                'insight' => null,
                'error' => 'Could not reach the Claude API: '.$e->getMessage(),
            ]);
        }
    }

    private function openAiInsight(StoreSetting $settings, array $stats): JsonResponse
    {
        try {
            $response = Http::withToken($settings->openai_api_key)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $settings->openai_model ?: 'gpt-4o-mini',
                    'max_tokens' => 1024,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => json_encode($stats, JSON_PRETTY_PRINT)],
                    ],
                ]);

            if (! $response->successful()) {
                return response()->json([
                    'configured' => true,
                    'insight' => null,
                    'error' => 'OpenAI API error: '.($response->json('error.message') ?? $response->body()),
                ]);
            }

            $text = trim((string) $response->json('choices.0.message.content'));

            if ($text === '') {
                return response()->json([
                    'configured' => true,
                    'insight' => null,
                    'error' => 'OpenAI declined to generate a summary for this data.',
                ]);
            }

            return response()->json([
                'configured' => true,
                'insight' => $text,
                'error' => null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'configured' => true,
                'insight' => null,
                'error' => 'Could not reach the OpenAI API: '.$e->getMessage(),
            ]);
        }
    }
}
