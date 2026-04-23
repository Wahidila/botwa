<?php

namespace BotWA;

class WahaClient
{
    private string $apiUrl;
    private string $apiKey;
    private string $session;

    public function __construct()
    {
        $this->apiUrl = rtrim(Config::get('waha_api_url', ''), '/');
        $this->apiKey = Config::get('waha_api_key', '');
        $this->session = Config::get('waha_session', 'default');
    }

    /**
     * Send a text message to a chat
     */
    public function sendMessage(string $chatId, string $text): ?array
    {
        return $this->request('POST', '/api/sendText', [
            'chatId' => $chatId,
            'text' => $text,
            'session' => $this->session,
        ]);
    }

    /**
     * Send typing indicator (seen + typing)
     */
    public function sendSeen(string $chatId): ?array
    {
        return $this->request('POST', '/api/sendSeen', [
            'chatId' => $chatId,
            'session' => $this->session,
        ]);
    }

    /**
     * Start typing indicator
     */
    public function startTyping(string $chatId): ?array
    {
        return $this->request('POST', '/api/startTyping', [
            'chatId' => $chatId,
            'session' => $this->session,
        ]);
    }

    /**
     * Stop typing indicator
     */
    public function stopTyping(string $chatId): ?array
    {
        return $this->request('POST', '/api/stopTyping', [
            'chatId' => $chatId,
            'session' => $this->session,
        ]);
    }

    /**
     * Send a reply to a specific message
     */
    public function sendReply(string $chatId, string $text, string $replyToMessageId): ?array
    {
        return $this->request('POST', '/api/sendText', [
            'chatId' => $chatId,
            'text' => $text,
            'session' => $this->session,
            'reply_to' => $replyToMessageId,
        ]);
    }

    /**
     * Send reaction to a message
     */
    public function sendReaction(string $chatId, string $messageId, string $reaction): ?array
    {
        return $this->request('POST', '/api/reaction', [
            'chatId' => $chatId,
            'messageId' => $messageId,
            'reaction' => $reaction,
            'session' => $this->session,
        ]);
    }

    /**
     * Get group participants (who's currently in the group)
     * Returns array of: [{ "id": "628xxx@c.us", "role": "participant|admin|superadmin" }]
     */
    public function getGroupParticipants(string $groupId): ?array
    {
        $encodedId = str_replace('@', '%40', $groupId);
        return $this->request('GET', '/api/' . $this->session . '/groups/' . $encodedId . '/participants/v2');
    }

    /**
     * Get group info
     */
    public function getGroupInfo(string $groupId): ?array
    {
        $encodedId = str_replace('@', '%40', $groupId);
        return $this->request('GET', '/api/' . $this->session . '/groups/' . $encodedId);
    }

    /**
     * Check if session is active
     */
    public function checkSession(): ?array
    {
        return $this->request('GET', '/api/sessions/' . $this->session);
    }

    /**
     * Make HTTP request to WAHA API
     */
    private function request(string $method, string $endpoint, array $data = []): ?array
    {
        $url = $this->apiUrl . $endpoint;

        $ch = curl_init();
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (!empty($this->apiKey)) {
            $headers[] = 'X-Api-Key: ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::error("WAHA request failed: {$error}", [
                'url' => $url,
                'method' => $method,
            ]);
            return null;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            Logger::error("WAHA API error: HTTP {$httpCode}", [
                'url' => $url,
                'response' => $result,
            ]);
            return null;
        }

        Logger::debug("WAHA request success", [
            'endpoint' => $endpoint,
            'httpCode' => $httpCode,
        ]);

        return $result;
    }
}
