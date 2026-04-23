<?php

namespace BotWA;

/**
 * Firecrawl Search API client
 * 
 * Uses POST https://api.firecrawl.dev/v2/search to search the web
 * and get clean markdown content from results.
 * 
 * Free plan: 500 credits total. Search = 2 credits per 10 results.
 */
class FirecrawlSearch
{
    private const API_URL = 'https://api.firecrawl.dev/v2/search';

    private string $apiKey;
    private bool $enabled;
    private int $maxResults;

    /** Keywords that indicate user wants to search the web */
    private const SEARCH_KEYWORDS = [
        'cari', 'cariin', 'carikan', 'search', 'googling', 'google',
        'berita', 'news', 'kabar', 'info terbaru', 'update terbaru',
        'apa itu', 'siapa itu', 'kapan', 'dimana',
        'terbaru', 'hari ini', 'terkini', 'latest',
        'harga', 'cuaca', 'jadwal', 'skor', 'hasil',
        'trending', 'viral',
    ];

    public function __construct()
    {
        $this->apiKey = Config::get('firecrawl_api_key', '');
        $this->enabled = (bool) Config::get('firecrawl_enabled', true);
        $this->maxResults = (int) Config::get('firecrawl_max_results', 5);
    }

    /**
     * Check if web search is available (has API key and enabled)
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

        $text = strtolower(trim($messageText));

        foreach (self::SEARCH_KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract search query from message text
     * Removes trigger words and search keywords to get the actual query
     */
    public function extractQuery(string $messageText): string
    {
        $text = trim($messageText);

        // Remove bot trigger words
        $text = preg_replace('/^(hey\s+)?(cil|bocil|cimol|mol)\s*[,.]?\s*/i', '', $text);

        // Remove common search prefixes
        $text = preg_replace('/^(tolong\s+)?(cari(?:in|kan)?|search|googling?|google)\s*/i', '', $text);
        $text = preg_replace('/^(apa\s+)?(berita|news|kabar|info)\s*(terbaru|terkini|hari\s*ini)?\s*(tentang|soal|mengenai)?\s*/i', '', $text);

        $text = trim($text);

        // If cleaned text is too short, use original (minus trigger words)
        if (mb_strlen($text) < 3) {
            $text = preg_replace('/^(hey\s+)?(cil|bocil|cimol|mol)\s*[,.]?\s*/i', '', trim($messageText));
        }

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

        Logger::info("Firecrawl search", ['query' => $query]);

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

        if (!$response || !isset($response['data'])) {
            Logger::error("Firecrawl search failed", [
                'query' => $query,
                'elapsed_ms' => $elapsed,
                'error' => $response['error'] ?? 'unknown',
            ]);
            return null;
        }

        $results = $response['data'];

        if (empty($results)) {
            Logger::info("Firecrawl: no results", ['query' => $query]);
            return null;
        }

        Logger::info("Firecrawl search success", [
            'query' => $query,
            'results' => count($results),
            'elapsed_ms' => $elapsed,
        ]);

        // Format results for AI context
        return $this->formatResults($query, $results);
    }

    /**
     * Format search results into AI-readable context
     */
    private function formatResults(string $query, array $results): string
    {
        $lines = [
            "HASIL PENCARIAN WEB untuk \"{$query}\":",
            "(Data real-time dari internet, gunakan ini untuk menjawab)",
            "",
        ];

        foreach ($results as $i => $result) {
            $num = $i + 1;
            $title = $result['title'] ?? 'Tanpa judul';
            $url = $result['url'] ?? '';
            $markdown = $result['markdown'] ?? $result['description'] ?? '';

            // Truncate markdown to avoid token explosion
            if (mb_strlen($markdown) > 800) {
                $markdown = mb_substr($markdown, 0, 800) . '...';
            }

            $lines[] = "--- Hasil {$num}: {$title} ---";
            if (!empty($url)) {
                $lines[] = "Sumber: {$url}";
            }
            if (!empty($markdown)) {
                $lines[] = $markdown;
            }
            $lines[] = "";
        }

        $lines[] = "---";
        $lines[] = "INSTRUKSI: Rangkum informasi di atas dengan gaya bahasa kamu (casual, Gen Z). Jangan copy-paste mentah. Sebutkan sumber jika relevan.";

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
            CURLOPT_TIMEOUT => 30,
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
        curl_close($ch);

        if ($error) {
            Logger::error("Firecrawl curl error: {$error}");
            return null;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            Logger::error("Firecrawl HTTP {$httpCode}", [
                'error' => $result['error'] ?? mb_substr($response, 0, 300),
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

        $result = $this->search('test');

        if ($result !== null) {
            return [
                'success' => true,
                'message' => 'Firecrawl connected! Search working.',
                'preview' => mb_substr($result, 0, 300),
            ];
        }

        return [
            'success' => false,
            'message' => 'Firecrawl search failed. Check API key.',
        ];
    }
}
