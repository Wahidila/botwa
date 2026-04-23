<?php

namespace BotWA;

/**
 * Firecrawl Search API client
 * 
 * Uses POST https://api.firecrawl.dev/v2/search to search the web
 * and get clean markdown content from results.
 */
class FirecrawlSearch
{
    private const API_URL = 'https://api.firecrawl.dev/v2/search';

    private string $apiKey;
    private bool $enabled;
    private int $maxResults;

    /** Keywords that indicate user wants to search the web */
    private const SEARCH_KEYWORDS = [
        'cari ', 'cariin ', 'carikan ', 'search ',
        'googling', 'google ',
        'berita ', 'news ',
        'info terbaru', 'update terbaru', 'kabar terbaru',
        'terbaru hari ini', 'terkini',
        'harga ', 'cuaca ', 'jadwal ', 'skor ',
        'trending', 'viral',
        'apa itu ', 'siapa itu ',
    ];

    public function __construct()
    {
        $this->apiKey = Config::get('firecrawl_api_key', '');
        $this->enabled = (bool) Config::get('firecrawl_enabled', true);
        $this->maxResults = (int) Config::get('firecrawl_max_results', 5);
    }

    /**
     * Check if web search is available
     */
    public function isAvailable(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    /**
     * Detect if a message needs web search
     */
    public function needsSearch(string $messageText): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        // Remove trigger words first
        $text = preg_replace('/^(hey\s+)?(cil|bocil|cimol|mol)\s*[,.]?\s*/i', '', $messageText);
        $text = strtolower(trim($text));

        foreach (self::SEARCH_KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a good search query from user message
     * Keep the intent clear, add date context
     */
    public function extractQuery(string $messageText): string
    {
        $text = trim($messageText);

        // Remove bot trigger words only
        $text = preg_replace('/^(hey\s+)?(cil|bocil|cimol|mol)\s*[,.]?\s*/i', '', $text);

        // Remove only the command prefix (cari/search), keep the rest intact
        $text = preg_replace('/^(tolong\s+)?(cari(?:in|kan)?|search|googling?)\s+/i', '', $text);

        $text = trim($text);

        // If text mentions "hari ini" / "terbaru" / "terkini", append today's date for better results
        $hasTimeRef = preg_match('/hari\s*ini|terbaru|terkini|latest|today/i', $text);
        if ($hasTimeRef) {
            $today = date('j F Y'); // e.g. "23 April 2026"
            $text .= " {$today}";
        }

        // If query is too short, use original message
        if (mb_strlen($text) < 5) {
            $text = preg_replace('/^(hey\s+)?(cil|bocil|cimol|mol)\s*[,.]?\s*/i', '', trim($messageText));
        }

        Logger::debug("Firecrawl query extracted", [
            'original' => $messageText,
            'query' => $text,
        ]);

        return trim($text);
    }

    /**
     * Search the web via Firecrawl API
     * Returns formatted context string for AI prompt, or null on failure
     */
    public function search(string $query): ?string
    {
        if (empty($query) || !$this->isAvailable()) {
            return null;
        }

        Logger::info("Firecrawl search starting", ['query' => $query]);

        $startTime = microtime(true);

        $payload = [
            'query' => $query,
            'limit' => $this->maxResults,
            'lang' => 'id',
            'country' => 'id',
            'scrapeOptions' => [
                'formats' => ['markdown'],
                'onlyMainContent' => true,
            ],
        ];

        $response = $this->request($payload);
        $elapsed = round((microtime(true) - $startTime) * 1000);

        if (!$response) {
            Logger::error("Firecrawl: no response", ['query' => $query, 'elapsed_ms' => $elapsed]);
            return null;
        }

        if (isset($response['error'])) {
            Logger::error("Firecrawl API error", [
                'query' => $query,
                'error' => $response['error'],
                'elapsed_ms' => $elapsed,
            ]);
            return null;
        }

        if (!isset($response['data']) || !is_array($response['data']) || empty($response['data'])) {
            Logger::warning("Firecrawl: empty results", [
                'query' => $query,
                'elapsed_ms' => $elapsed,
                'response_keys' => is_array($response) ? array_keys($response) : 'not_array',
            ]);
            return null;
        }

        $results = $response['data'];

        Logger::info("Firecrawl search success", [
            'query' => $query,
            'results' => count($results),
            'elapsed_ms' => $elapsed,
            'first_title' => $results[0]['title'] ?? 'N/A',
        ]);

        return $this->formatResults($query, $results);
    }

    /**
     * Format search results into AI-readable context
     */
    private function formatResults(string $query, array $results): string
    {
        $today = date('j F Y');

        $lines = [
            "=== HASIL PENCARIAN WEB (real-time dari internet, tanggal hari ini: {$today}) ===",
            "Query: \"{$query}\"",
            "Jumlah hasil: " . count($results),
            "",
        ];

        foreach ($results as $i => $result) {
            $num = $i + 1;
            $title = $result['title'] ?? 'Tanpa judul';
            $url = $result['url'] ?? '';
            $markdown = $result['markdown'] ?? $result['description'] ?? '';

            // Truncate markdown to avoid token explosion (keep more for better context)
            if (mb_strlen($markdown) > 1000) {
                $markdown = mb_substr($markdown, 0, 1000) . '... [dipotong]';
            }

            $lines[] = "[Hasil {$num}] {$title}";
            if (!empty($url)) {
                $lines[] = "Sumber: {$url}";
            }
            if (!empty($markdown)) {
                $lines[] = $markdown;
            }
            $lines[] = "";
        }

        $lines[] = "=== AKHIR HASIL PENCARIAN ===";
        $lines[] = "";
        $lines[] = "INSTRUKSI PENTING:";
        $lines[] = "- Data di atas adalah HASIL PENCARIAN INTERNET REAL-TIME, BUKAN dari pengetahuan kamu.";
        $lines[] = "- GUNAKAN data di atas untuk menjawab pertanyaan user.";
        $lines[] = "- JANGAN mengarang atau menambah informasi yang tidak ada di hasil pencarian.";
        $lines[] = "- Rangkum dengan gaya bahasa kamu (casual, Gen Z) tapi FAKTA harus dari data di atas.";
        $lines[] = "- Sebutkan sumber berita jika relevan (nama media, bukan URL panjang).";

        return implode("\n", $lines);
    }

    /**
     * Make HTTP request to Firecrawl API
     */
    private function request(array $data): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($error) {
            Logger::error("Firecrawl curl error: [{$errno}] {$error}");
            return null;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            Logger::error("Firecrawl HTTP {$httpCode}", [
                'error' => $result['error'] ?? mb_substr($response, 0, 500),
            ]);
            return $result; // Return so caller can see error message
        }

        if ($result === null && !empty($response)) {
            Logger::error("Firecrawl: invalid JSON response", [
                'raw' => mb_substr($response, 0, 300),
            ]);
            return null;
        }

        return $result;
    }

    /**
     * Test connection to Firecrawl
     */
    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'Firecrawl API key is empty.'];
        }

        $response = $this->request([
            'query' => 'berita Indonesia hari ini ' . date('j F Y'),
            'limit' => 2,
            'lang' => 'id',
            'country' => 'id',
        ]);

        if ($response === null) {
            return [
                'success' => false,
                'message' => 'Firecrawl connection failed. No response from API.',
            ];
        }

        if (isset($response['error'])) {
            $errMsg = is_string($response['error']) ? $response['error'] : json_encode($response['error']);
            return [
                'success' => false,
                'message' => 'Firecrawl error: ' . $errMsg,
            ];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            $count = count($response['data']);
            $firstTitle = $response['data'][0]['title'] ?? 'N/A';
            return [
                'success' => true,
                'message' => "Firecrawl connected! Got {$count} results.",
                'preview' => "First result: \"{$firstTitle}\"",
            ];
        }

        return [
            'success' => false,
            'message' => 'Firecrawl: unexpected response format.',
        ];
    }
}
