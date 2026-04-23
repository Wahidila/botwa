<?php

namespace BotWA;

class MessageHandler
{
    private WahaClient $waha;
    private AIProvider|KimiProvider $ai;
    private PersonalityEngine $personality;
    private MemoryManager $memory;
    private SkillManager $skills;
    private TriggerManager $triggers;
    private GameEngine $games;
    private Database $db;

    public function __construct()
    {
        $this->waha = new WahaClient();
        $this->ai = AIFactory::create();
        $this->personality = new PersonalityEngine();
        $this->memory = new MemoryManager();
        $this->skills = new SkillManager();
        $this->triggers = new TriggerManager();
        $this->games = new GameEngine();
        $this->db = Database::getInstance();
    }

    /**
     * Handle incoming webhook from WAHA
     */
    public function handle(array $payload): void
    {
        $event = $payload['event'] ?? '';

        // Only process message events
        if ($event !== 'message') {
            Logger::debug("Ignoring event: {$event}");
            return;
        }

        $body = $payload['payload'] ?? $payload;
        $messageData = $this->parseMessage($body);

        if (!$messageData) {
            Logger::debug("Could not parse message data");
            return;
        }

        // Skip messages from bot itself
        if ($messageData['fromMe']) {
            return;
        }

        // Private chat: only respond to registered members
        if (!($messageData['isGroup'] ?? false)) {
            if (!$this->isRegisteredMember($messageData['senderPhone'])) {
                Logger::debug("Ignoring private chat from unregistered: " . ($messageData['senderPhone'] ?? 'unknown'));
                return;
            }
            Logger::info("Private chat from registered member: " . $this->getMemberNickname($messageData['senderPhone']));
        }

        // Check if bot is enabled
        if (!Config::get('bot_enabled', true)) {
            Logger::info("Bot is disabled, skipping message");
            return;
        }

        // Log incoming message
        $this->logMessage($messageData, 'incoming');

        // Store in conversation history
        $senderNickname = $this->getMemberNickname($messageData['senderPhone']);
        $this->memory->addToHistory(
            $messageData['chatId'],
            'user',
            $messageData['senderPhone'],
            $senderNickname,
            $messageData['text']
        );

        // Check if message triggers the bot
        // In private chat with registered member: always triggered, no trigger word needed
        $isPrivateChat = !($messageData['isGroup'] ?? false);
        $triggerResult = $this->triggers->check($messageData['text']);

        if (!$triggerResult['triggered'] && !$isPrivateChat) {
            Logger::debug("Message not triggered in group, skipping");
            return;
        }

        // Force trigger for private chats
        if ($isPrivateChat && !$triggerResult['triggered']) {
            $triggerResult = [
                'triggered' => true,
                'trigger' => null,
                'response_mode' => 'ai',
                'cleaned_text' => $messageData['text'],
            ];
        }

        // Process response chance
        $responseChance = (int) Config::get('bot_response_chance', 100);
        if ($responseChance < 100 && rand(1, 100) > $responseChance) {
            Logger::info("Skipped by response chance ({$responseChance}%)");
            return;
        }

        // Show typing indicator
        $this->simulateTyping($messageData['chatId']);

        // Build AI messages
        $aiMessages = $this->buildAIMessages($messageData, $triggerResult);

        // Get AI response
        $aiResult = $this->ai->chat($aiMessages);

        if (!$aiResult || empty($aiResult['content'])) {
            Logger::error("AI returned empty response");
            return;
        }

        $responseText = $aiResult['content'];

        // Send response via WAHA
        $this->waha->stopTyping($messageData['chatId']);
        $this->waha->sendMessage($messageData['chatId'], $responseText);

        // Store bot response in conversation history
        $this->memory->addToHistory(
            $messageData['chatId'],
            'assistant',
            Config::get('bot_phone', ''),
            Config::get('bot_name', 'Cimol'),
            $responseText
        );

        // Log outgoing message
        $this->logMessage(
            array_merge($messageData, ['text' => $responseText]),
            'outgoing',
            $aiMessages,
            $aiResult
        );

        // Auto-extract and save important facts from conversation
        $this->memory->autoExtractFacts($messageData, $responseText);

        Logger::info("Response sent successfully", [
            'chatId' => $messageData['chatId'],
            'tokens' => $aiResult['tokens_used'],
            'time_ms' => $aiResult['response_time_ms'],
        ]);
    }

    /**
     * Parse WAHA webhook message payload
     */
    private function parseMessage(array $body): ?array
    {
        // Log raw payload keys for debugging
        Logger::debug("Raw webhook payload keys", [
            'keys' => array_keys($body),
            'has_payload' => isset($body['payload']),
        ]);

        // WAHA GOWS may wrap data in 'payload'
        $msg = $body['payload'] ?? $body;

        // Log message structure for debugging
        Logger::debug("Message structure", [
            'msg_keys' => array_keys($msg),
            'has_key' => isset($msg['key']),
            'has_from' => isset($msg['from']),
            'has_chatId' => isset($msg['chatId']),
            'has_participant' => isset($msg['participant']),
            'has_body' => isset($msg['body']),
            'has_text' => isset($msg['text']),
        ]);

        $id = $msg['id'] ?? $msg['key']['id'] ?? null;
        $fromMe = $msg['fromMe'] ?? $msg['key']['fromMe'] ?? false;
        $chatId = $msg['from'] ?? $msg['chatId'] ?? $msg['key']['remoteJid'] ?? null;

        // Sender phone: try multiple fields
        $senderPhone = $msg['participant'] ?? $msg['_data']['author'] ?? '';
        // In private chat, sender = from
        if (empty($senderPhone)) {
            $senderPhone = $msg['from'] ?? '';
        }

        // Text: try multiple fields
        $text = $msg['body'] ?? $msg['text'] ?? '';
        if (empty($text) && isset($msg['message']['conversation'])) {
            $text = $msg['message']['conversation'];
        }
        if (empty($text) && isset($msg['message']['extendedTextMessage']['text'])) {
            $text = $msg['message']['extendedTextMessage']['text'];
        }

        if (empty($text) || empty($chatId)) {
            Logger::debug("Skipping: empty text or chatId", [
                'text_empty' => empty($text),
                'chatId_empty' => empty($chatId),
            ]);
            return null;
        }

        // Keep raw sender ID for LID detection
        $rawSenderId = $senderPhone;
        $senderIsLid = self::isLid($rawSenderId);
        // Clean: remove @xxx suffix, keep digits only
        $senderPhone = self::normalizePhone($rawSenderId);

        Logger::debug("Parsed message", [
            'chatId' => $chatId,
            'rawSenderId' => $rawSenderId,
            'senderPhone' => $senderPhone,
            'senderIsLid' => $senderIsLid,
            'isGroup' => str_contains($chatId, '@g.us'),
            'text_preview' => mb_substr($text, 0, 50),
        ]);

        return [
            'id' => $id,
            'fromMe' => (bool) $fromMe,
            'chatId' => $chatId,
            'senderPhone' => $senderPhone,
            'senderIsLid' => $senderIsLid,
            'text' => $text,
            'isGroup' => str_contains($chatId, '@g.us'),
            'timestamp' => $msg['timestamp'] ?? time(),
        ];
    }

    /**
     * Normalize phone/lid identifier
     * Returns the raw numeric ID (could be phone or LID)
     */
    public static function normalizePhone(string $phone): string
    {
        // Remove @s.whatsapp.net, @c.us, @lid, @g.us, etc
        $phone = preg_replace('/@.*$/', '', $phone);
        // Remove any non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        return $phone;
    }

    /**
     * Check if an identifier is a LID (not a phone number)
     * LIDs are typically longer and don't start with country codes
     */
    public static function isLid(string $identifier): bool
    {
        // Original raw value contains @lid
        return str_contains($identifier, '@lid');
    }

    /**
     * Build AI messages array with system prompt, memory, and context
     */
    private function buildAIMessages(array $messageData, array $triggerResult): array
    {
        $messages = [];

        // 0. Fetch real-time group participants from WAHA
        $groupParticipants = null;
        if ($messageData['isGroup'] ?? false) {
            try {
                $groupParticipants = $this->waha->getGroupParticipants($messageData['chatId']);
                Logger::debug("Fetched group participants", [
                    'chatId' => $messageData['chatId'],
                    'count' => is_array($groupParticipants) ? count($groupParticipants) : 0,
                ]);
            } catch (\Throwable $e) {
                Logger::warning("Failed to fetch group participants: " . $e->getMessage());
            }
        }

        // 1. System prompt (personality + group awareness)
        $systemPrompt = $this->personality->buildSystemPrompt($messageData, $groupParticipants);
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // 2. Relevant memories
        $memories = $this->memory->getRelevantMemories($messageData['text']);
        if (!empty($memories)) {
            $memoryContext = "KONTEKS YANG KAMU INGAT:\n";
            foreach ($memories as $mem) {
                $memoryContext .= "- {$mem['content']}\n";
            }
            $messages[] = ['role' => 'system', 'content' => $memoryContext];
        }

        // 3. Active skills context
        $skillContext = $this->skills->getActiveSkillsContext($messageData['text'], $triggerResult);
        if (!empty($skillContext)) {
            $messages[] = ['role' => 'system', 'content' => $skillContext];
        }

        // 4. Conversation history
        $memoryLimit = (int) Config::get('bot_memory_limit', 20);
        $history = $this->memory->getHistory($messageData['chatId'], $memoryLimit);
        foreach ($history as $msg) {
            $role = $msg['role'];
            $content = $msg['content'];

            // Prefix user messages with nickname for context
            if ($role === 'user' && !empty($msg['sender_nickname'])) {
                $content = "[{$msg['sender_nickname']}]: {$content}";
            }

            $messages[] = ['role' => $role, 'content' => $content];
        }

        // 5. Current message (already in history, but ensure it's the last)
        // The history already includes the current message, so we don't add it again

        return $messages;
    }

    /**
     * Get member nickname by phone number or LID
     */
    private function getMemberNickname(string $identifier): string
    {
        $identifier = self::normalizePhone($identifier);
        if (empty($identifier)) {
            return 'seseorang';
        }

        try {
            // Try match by LID first
            $member = $this->db->fetchOne(
                "SELECT nickname FROM members WHERE lid = ? AND is_active = 1",
                [$identifier]
            );
            if ($member) {
                return $member['nickname'];
            }

            // Try match by phone number
            $member = $this->db->fetchOne(
                "SELECT nickname FROM members WHERE phone_number = ? AND is_active = 1",
                [$identifier]
            );
            if ($member) {
                return $member['nickname'];
            }

            // Try fuzzy match: last 10 digits
            $last10 = substr($identifier, -10);
            $member = $this->db->fetchOne(
                "SELECT nickname FROM members WHERE phone_number LIKE ? OR lid LIKE ? AND is_active = 1 LIMIT 1",
                ['%' . $last10, '%' . $last10]
            );
            if ($member) {
                return $member['nickname'];
            }

            Logger::debug("No member found", ['identifier' => $identifier]);
            return 'seseorang';
        } catch (\Exception $e) {
            return 'seseorang';
        }
    }

    /**
     * Check if a phone/LID belongs to a registered member (not bot role)
     */
    private function isRegisteredMember(string $identifier): bool
    {
        $identifier = self::normalizePhone($identifier);
        if (empty($identifier)) {
            return false;
        }

        try {
            $member = $this->db->fetchOne(
                "SELECT id, role FROM members WHERE (lid = ? OR phone_number = ?) AND is_active = 1 AND role != 'bot'",
                [$identifier, $identifier]
            );
            return $member !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Simulate typing with delay
     */
    private function simulateTyping(string $chatId): void
    {
        $delay = (int) Config::get('bot_typing_delay', 1);
        $this->waha->sendSeen($chatId);
        $this->waha->startTyping($chatId);

        if ($delay > 0) {
            sleep($delay);
        }
    }

    /**
     * Log message to database
     */
    private function logMessage(array $messageData, string $type, ?array $prompt = null, ?array $aiResult = null): void
    {
        try {
            $this->db->insert('chat_logs', [
                'message_id' => $messageData['id'] ?? null,
                'chat_id' => $messageData['chatId'],
                'sender_phone' => $messageData['senderPhone'] ?? null,
                'sender_name' => $this->getMemberNickname($messageData['senderPhone'] ?? ''),
                'message_text' => $messageData['text'],
                'message_type' => $type,
                'is_triggered' => $type === 'outgoing' ? 1 : 0,
                'ai_prompt_used' => $prompt ? json_encode($prompt, JSON_UNESCAPED_UNICODE) : null,
                'ai_response' => $aiResult ? ($aiResult['content'] ?? null) : null,
                'tokens_used' => $aiResult['tokens_used'] ?? null,
                'response_time_ms' => $aiResult['response_time_ms'] ?? null,
            ]);
        } catch (\Exception $e) {
            Logger::error("Failed to log message: " . $e->getMessage());
        }
    }
}
