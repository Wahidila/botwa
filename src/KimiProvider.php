<?php

namespace BotWA;

/**
 * Kimi K2.5/K2.6 Provider with native $web_search support
 * 
 * Kimi API is OpenAI-compatible but has a special builtin_function
 * tool type for $web_search. The search is executed by Kimi itself -
 * we just need to loop back the arguments.
 * 
 * Flow:
 * 1. Send request with tools: [{ type: "builtin_function", function: { name: "$web_search" } }]
 * 2. If finish_reason = "tool_calls" → Kimi wants to search
 * 3. Extract tool_call arguments, send back as role=tool message
 * 4. Kimi executes search internally, returns final answer with finish_reason = "stop"
 */
class KimiProvider
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private float $temperature;
    private int $maxTokens;
    private bool $webSearchEnabled;

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::get('ai_base_url', ''), '/');
        $this->apiKey = Config::get('ai_api_key', '');
        $this->model = Config::get('ai_model', 'kimi-k2.5');
        $this->temperature = (float) Config::get('ai_temperature', 0.8);
        $this->maxTokens = (int) Config::get('ai_max_tokens', 4096);
        $this->webSearchEnabled = (bool) Config::get('ai_web_search', false);
    }

    /**
     * Send chat completion with optional web search tool_calls loop
     */
    public function chat(array $messages): ?array
    {
        $startTime = microtime(true);
        $totalTokens = 0;

        // Build initial payload
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ];

        // Add $web_search tool if enabled
        if ($this->webSearchEnabled) {
            $payload['tools'] = [
                [
                    'type' => 'builtin_function',
                    'function' => [
                        'name' => '$web_search',
                    ],
                ],
            ];
        }

        // Tool calls loop (max 5 rounds to prevent infinite loop)
        $maxRounds = 5;
        $round = 0;
        $response = null;

        while ($round < $maxRounds) {
            $round++;
            $response = $this->request('/v1/chat/completions', $payload);

            if (!$response || !isset($response['_body'])) {
                Logger::error("Kimi request failed at round {$round}");
                break;
            }

            $body = $response['_body'];
            $finishReason = $body['choices'][0]['finish_reason'] ?? 'stop';
            $totalTokens += $body['usage']['total_tokens'] ?? 0;

            // If stop → we have the final answer
            if ($finishReason === 'stop') {
                $content = $body['choices'][0]['message']['content'] ?? null;
                $elapsed = round((microtime(true) - $startTime) * 1000);

                Logger::info("Kimi response received", [
                    'model' => $this->model,
                    'tokens' => $totalTokens,
                    'rounds' => $round,
                    'web_search' => $this->webSearchEnabled,
                    'elapsed_ms' => $elapsed,
                ]);

                return [
                    'content' => $content,
                    'tokens_used' => $totalTokens,
                    'response_time_ms' => $elapsed,
                    'model' => $this->model,
                ];
            }

            // If tool_calls → Kimi wants to use $web_search
            if ($finishReason === 'tool_calls') {
                $assistantMessage = $body['choices'][0]['message'] ?? null;
                if (!$assistantMessage || empty($assistantMessage['tool_calls'])) {
                    Logger::error("Kimi tool_calls but no tool_calls data");
                    break;
                }

                Logger::debug("Kimi requesting web search", [
                    'round' => $round,
                    'tool_calls_count' => count($assistantMessage['tool_calls']),
                ]);

                // Add assistant message to conversation
                $payload['messages'][] = [
                    'role' => 'assistant',
                    'content' => $assistantMessage['content'] ?? null,
                    'tool_calls' => $assistantMessage['tool_calls'],
                ];

                // Loop through each tool call and send back arguments
                foreach ($assistantMessage['tool_calls'] as $toolCall) {
                    $toolName = $toolCall['function']['name'] ?? '';
                    $toolArgs = $toolCall['function']['arguments'] ?? '{}';
                    $toolCallId = $toolCall['id'] ?? '';

                    if ($toolName === '$web_search') {
                        // For $web_search: just return arguments as-is
                        // Kimi will execute the search internally
                        $payload['messages'][] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCallId,
                            'name' => $toolName,
                            'content' => $toolArgs,
                        ];

                        Logger::debug("Sent $web_search arguments back to Kimi", [
                            'tool_call_id' => $toolCallId,
                        ]);
                    } else {
                        // Unknown tool, send error
                        $payload['messages'][] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCallId,
                            'name' => $toolName,
                            'content' => json_encode(['error' => "Unknown tool: {$toolName}"]),
                        ];
                    }
                }

                // Continue loop → send updated messages back to Kimi
                continue;
            }

            // Unknown finish_reason, break
            Logger::warning("Kimi unknown finish_reason: {$finishReason}");
            break;
        }

        if ($round >= $maxRounds) {
            Logger::warning("Kimi hit max tool_calls rounds ({$maxRounds})");
        }

        // If we got here without a proper response, try to extract whatever we have
        $elapsed = round((microtime(true) - $startTime) * 1000);
        if ($response && isset($response['_body']['choices'][0]['message']['content'])) {
            return [
                'content' => $response['_body']['choices'][0]['message']['content'],
                'tokens_used' => $totalTokens,
                'response_time_ms' => $elapsed,
                'model' => $this->model,
            ];
        }

        return null;
    }

    /**
     * Test connection
     */
    public function testConnection(): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'message' => 'Base URL is empty.'];
        }
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'API Key is empty.'];
        }

        // Test basic chat first
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => 'Say "OK" if you can hear me.'],
            ],
            'max_tokens' => 10,
            'temperature' => 0.1,
        ];

        $response = $this->request('/v1/chat/completions', $payload);
        $fullUrl = $this->buildUrl('/v1/chat/completions');

        if (!$response || !isset($response['_body'])) {
            return [
                'success' => false,
                'message' => 'Connection failed. No response.',
                'debug' => ['url_used' => $fullUrl, 'curl_error' => $response['_curl_error'] ?? ''],
            ];
        }

        $httpCode = $response['_http_code'] ?? 0;
        $body = $response['_body'];

        if (!empty($response['_curl_error'])) {
            return [
                'success' => false,
                'message' => 'cURL error: ' . $response['_curl_error'],
                'debug' => ['url_used' => $fullUrl],
            ];
        }

        if ($httpCode >= 400) {
            $errorMsg = $body['error']['message'] ?? $body['error'] ?? $response['_raw'] ?? '';
            return [
                'success' => false,
                'message' => "HTTP {$httpCode}: " . (is_string($errorMsg) ? $errorMsg : json_encode($errorMsg)),
                'debug' => ['url_used' => $fullUrl, 'http_code' => $httpCode, 'response' => $body],
            ];
        }

        $content = $body['choices'][0]['message']['content'] ?? null;
        if ($content) {
            $result = [
                'success' => true,
                'message' => 'Connected! Model: ' . $this->model,
                'response' => $content,
                'tokens' => $body['usage']['total_tokens'] ?? null,
                'debug' => [
                    'url_used' => $fullUrl,
                    'model' => $this->model,
                    'web_search_enabled' => $this->webSearchEnabled,
                    'provider' => 'kimi',
                ],
            ];

            // If web search enabled, test that too
            if ($this->webSearchEnabled) {
                $searchTest = $this->testWebSearch();
                $result['web_search_test'] = $searchTest;
            }

            return $result;
        }

        return [
            'success' => false,
            'message' => 'Got response but unexpected format.',
            'debug' => ['url_used' => $fullUrl, 'response' => $body],
        ];
    }

    /**
     * Quick test for web search capability
     */
    private function testWebSearch(): array
    {
        $result = $this->chat([
            ['role' => 'user', 'content' => 'What is today\'s date? Search the web to confirm.'],
        ]);

        if ($result && !empty($result['content'])) {
            return [
                'success' => true,
                'message' => 'Web search working!',
                'response_preview' => mb_substr($result['content'], 0, 200),
                'tokens' => $result['tokens_used'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Web search test failed.',
        ];
    }

    /**
     * Build full URL
     */
    private function buildUrl(string $endpoint): string
    {
        $base = $this->baseUrl;
        if (preg_match('#/v1/?$#', $base)) {
            $base = rtrim($base, '/');
            $endpoint = preg_replace('#^/v1#', '', $endpoint);
        }
        if (str_contains($base, '/chat/completions')) {
            return $base;
        }
        return $base . $endpoint;
    }

    /**
     * Make HTTP request
     */
    private function request(string $endpoint, array $data): ?array
    {
        $url = $this->buildUrl($endpoint);

        Logger::debug("Kimi request", [
            'url' => $url,
            'model' => $data['model'] ?? 'unknown',
            'has_tools' => isset($data['tools']),
            'messages_count' => count($data['messages'] ?? []),
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($error) {
            Logger::error("Kimi curl failed: [{$errno}] {$error}", ['url' => $url]);
            return [
                '_body' => null,
                '_raw' => '',
                '_http_code' => 0,
                '_curl_error' => "[{$errno}] {$error}",
            ];
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            Logger::error("Kimi HTTP error: {$httpCode}", [
                'url' => $url,
                'response' => mb_substr($response, 0, 500),
            ]);
        }

        return [
            '_body' => $result,
            '_raw' => mb_substr($response, 0, 2000),
            '_http_code' => $httpCode,
            '_curl_error' => '',
        ];
    }
}
