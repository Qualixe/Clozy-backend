<?php

namespace App\Http\Controllers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\StoreSetting;
use App\Services\StoreAnalyticsService;
use App\Support\AiErrorMessage;
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
        $key = match ($settings->ai_provider) {
            'openai' => $settings->openai_api_key,
            'gemini' => $settings->gemini_api_key,
            default => $settings->anthropic_api_key,
        };

        if (! $key) {
            return response()->json([
                'configured' => false,
                'insight' => null,
                'error' => null,
            ]);
        }

        $stats = $this->analytics->summary(90);

        return match ($settings->ai_provider) {
            'openai' => $this->openAiCompatibleInsight(
                'https://api.openai.com/v1/chat/completions',
                $settings->openai_api_key,
                $settings->openai_model ?: 'gpt-4o-mini',
                'OpenAI',
                $stats,
            ),
            'gemini' => $this->openAiCompatibleInsight(
                'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
                $settings->gemini_api_key,
                $settings->gemini_model ?: 'gemini-flash-latest',
                'Gemini',
                $stats,
            ),
            default => $this->anthropicInsight($settings, $stats),
        };
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
                'error' => AiErrorMessage::forStatus($e->status ?? 500, 'Anthropic'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'configured' => true,
                'insight' => null,
                'error' => AiErrorMessage::forConnectionFailure('Anthropic'),
            ]);
        }
    }

    /**
     * Shared by OpenAI and Gemini — Gemini exposes an OpenAI-compatible
     * endpoint (`/v1beta/openai/...`) that accepts the exact same request
     * shape, so there's no need for a second hand-rolled client against
     * Gemini's native (differently-shaped) generateContent API.
     */
    private function openAiCompatibleInsight(
        string $baseUrl,
        ?string $apiKey,
        string $model,
        string $providerLabel,
        array $stats,
    ): JsonResponse {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post($baseUrl, [
                    'model' => $model,
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
                    'error' => AiErrorMessage::forStatus($response->status(), $providerLabel),
                ]);
            }

            $text = trim((string) $response->json('choices.0.message.content'));

            if ($text === '') {
                return response()->json([
                    'configured' => true,
                    'insight' => null,
                    'error' => "{$providerLabel} declined to generate a summary for this data.",
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
                'error' => AiErrorMessage::forConnectionFailure($providerLabel),
            ]);
        }
    }
}
