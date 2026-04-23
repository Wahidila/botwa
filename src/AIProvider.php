<?php

namespace BotWA;

class AIProvider
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private float $temperature;
    private int $maxTokens;
    private float $topP;
    private float $frequencyPenalty;
    private float $presencePenalty;

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::get('ai_base_url', ''), '/');
        $this->apiKey = Config::get('ai_api_key', '');
        $this->model = Config::get('ai_model', 'gpt-4o');
        $this->temperature = (float) Config::get('ai_temperature', 0.8);
        $this->maxTokens = (int) Config::get('ai_max_tokens', 1024);
        $this->topP = (float) Config::get('ai_top_p', 0.95);
        $this->frequencyPenalty = (float) Config::get('ai_frequency_penalty', 0.3);
        $this->presencePenalty = (float) Config::get('ai_presence_penalty', 0.3);
    }

    /**
     * Send chat completion request to AI provider
     */
    public function chat(array $messages): ?array
    {
        $startTime = microtime(true);

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'top_p' => $this->topP,
            'frequency_penalty' => $this->frequencyPenalty,
            'presence_penalty' => $this->presencePenalty,
        ];

        $response = $this->request('/v1/chat/completions', $payload);
        $elapsed = round((microtime(true) - $startTime) * 1000);

        if (!$response || !isset($response['_body'])) {
            Logger::error("AI chat completion failed", ['elapsed_ms' => $elapsed]);
            return null;
        }

        $body = $response['_body'];
        $content = $body['choices'][0]['message']['content'] ?? null;
        $tokensUsed = $body['usage']['total_tokens'] ?? null;

        Logger::info("AI response received", [
            'model' => $this->model,
            'tokens' => $tokensUsed,
            'elapsed_ms' => $elapsed,
        ]);

        return [
            'content' => $content,
            'tokens_used' => $tokensUsed,
            'response_time_ms' => $elapsed,
            'model' => $this->model,
        ];
    }

    /**
     * Test connection to AI provider - with detailed error info
     */
    public function testConnection(): array
    {
        // Validate config first
        if (empty($this->baseUrl)) {
            return ['success' => false, 'message' => 'Base URL is empty. Set it in AI Settings.'];
        }
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'API Key is empty. Set it in AI Settings.'];
        }
        if (empty($this->model)) {
            return ['success' => false, 'message' => 'Model name is empty. Set it in AI Settings.'];
        }

        // Build the full URL for debugging
        $fullUrl = $this->buildUrl('/v1/chat/completions');

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => 'Say "OK" if you can hear me.'],
            ],
            'max_tokens' => 10,
            'temperature' => 0.1,
        ];

        $response = $this->request('/v1/chat/completions', $payload);

        if (!$response) {
            return [
                'success' => false,
                'message' => 'Connection failed. No response received.',
                'debug' => [
                    'url_used' => $fullUrl,
                    'model' => $this->model,
                    'hint' => 'Check server logs for curl error details.',
                ],
            ];
        }

        $httpCode = $response['_http_code'] ?? 0;
        $curlError = $response['_curl_error'] ?? '';
        $body = $response['_body'] ?? null;
        $rawBody = $response['_raw'] ?? '';

        // Curl error
        if (!empty($curlError)) {
            return [
                'success' => false,
                'message' => "cURL error: {$curlError}",
                'debug' => [
                    'url_used' => $fullUrl,
                    'curl_error' => $curlError,
                ],
            ];
        }

        // HTTP error
        if ($httpCode >= 400) {
            $errorMsg = $body['error']['message'] ?? $body['error'] ?? $rawBody;
            return [
                'success' => false,
                'message' => "HTTP {$httpCode}: " . (is_string($errorMsg) ? $errorMsg : json_encode($errorMsg)),
                'debug' => [
                    'url_used' => $fullUrl,
                    'http_code' => $httpCode,
                    'response' => $body ?? $rawBody,
                ],
            ];
        }

        // Check for valid response
        $content = $body['choices'][0]['message']['content'] ?? null;
        if ($content) {
            return [
                'success' => true,
                'message' => 'Connected! Model: ' . $this->model,
                'response' => $content,
                'tokens' => $body['usage']['total_tokens'] ?? null,
                'debug' => [
                    'url_used' => $fullUrl,
                    'http_code' => $httpCode,
                    'model' => $this->model,
                ],
            ];
        }

        // Got response but unexpected format
        return [
            'success' => false,
            'message' => 'Got response but unexpected format.',
            'debug' => [
                'url_used' => $fullUrl,
                'http_code' => $httpCode,
                'response' => $body ?? $rawBody,
            ],
        ];
    }

    /**
     * Build full URL, handling cases where base URL already contains /v1
     */
    private function buildUrl(string $endpoint): string
    {
        $base = $this->baseUrl;

        // If base URL already ends with /v1, don't add /v1 again
        if (preg_match('#/v1/?$#', $base)) {
            $base = rtrim($base, '/');
            // endpoint = /v1/chat/completions -> strip /v1
            $endpoint = preg_replace('#^/v1#', '', $endpoint);
        }

        // If base URL already contains the full endpoint path, just use base
        if (str_contains($base, '/chat/completions')) {
            return $base;
        }

        return $base . $endpoint;
    }

    /**
     * Make HTTP request to AI provider
     */
    private function request(string $endpoint, array $data): ?array
    {
        $url = $this->buildUrl($endpoint);

        Logger::debug("AI request", [
            'url' => $url,
            'model' => $data['model'] ?? 'unknown',
            'base_url_config' => $this->baseUrl,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            /* 
             * SSL: try verify first, fallback handled by curl error check.
             * Some shared hostings have outdated CA bundles.
             */
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
            Logger::error("AI provider curl failed: [{$errno}] {$error}", [
                'url' => $url,
            ]);
            return [
                '_body' => null,
                '_raw' => '',
                '_http_code' => 0,
                '_curl_error' => "[{$errno}] {$error}",
            ];
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            Logger::error("AI provider HTTP error: {$httpCode}", [
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
