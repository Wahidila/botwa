<?php

namespace BotWA;

/**
 * Handles bot commands (actions that DO something, not just chat)
 * 
 * Commands are detected by pattern matching on the message text.
 * Only authorized roles (owner, princess) can execute commands.
 * 
 * Supported commands:
 * - chat/kirim/dm [nomor] [pesan]    → Send message to a phone number
 * - broadcast [pesan]                 → Send to all registered members (owner only)
 */
class CommandHandler
{
    private WahaClient $waha;
    private Database $db;

    /** Roles allowed to use commands */
    private const ALLOWED_ROLES = ['owner', 'princess'];

    public function __construct(WahaClient $waha)
    {
        $this->waha = $waha;
        $this->db = Database::getInstance();
    }

    /**
     * Check if a message is a command and execute it
     * Returns: ['is_command' => bool, 'response' => string|null]
     */
    public function handle(array $messageData): array
    {
        $text = trim($messageData['text'] ?? '');
        $senderPhone = $messageData['senderPhone'] ?? '';

        // Check if sender is authorized
        $senderRole = $this->getMemberRole($senderPhone);
        if (!in_array($senderRole, self::ALLOWED_ROLES)) {
            // Not authorized - don't reveal command exists, return not a command
            return ['is_command' => false, 'response' => null];
        }

        // Try to match command patterns
        $command = $this->parseCommand($text);

        if (!$command) {
            return ['is_command' => false, 'response' => null];
        }

        Logger::info("Command detected", [
            'command' => $command['type'],
            'sender' => $senderPhone,
            'role' => $senderRole,
        ]);

        // Execute command
        return match ($command['type']) {
            'send_message' => $this->executeSendMessage($command),
            'broadcast' => $senderRole === 'owner'
                ? $this->executeBroadcast($command)
                : ['is_command' => true, 'response' => 'sori, broadcast cuma bisa dipake sama owner ya 😅'],
            default => ['is_command' => false, 'response' => null],
        };
    }

    /**
     * Parse message text to detect command
     * Returns command array or null if not a command
     */
    private function parseCommand(string $text): ?array
    {
        // Normalize: remove trigger words first (mol, cil, cimol, hey cil, etc)
        $cleaned = preg_replace('/^(hey\s+)?(cil|bocil|cimol|mol)\s*[,.]?\s*/i', '', $text);
        $cleaned = trim($cleaned);

        if (empty($cleaned)) {
            return null;
        }

        // --- Command: Send message to someone ---
        // Patterns:
        //   "chat 628xxx bilang/kasih tau/sampaikan [pesan]"
        //   "kirim 628xxx [pesan]"
        //   "dm 628xxx [pesan]"
        //   "chat 628xxx "[pesan]""
        //   "tolong chat 628xxx [pesan]"
        //   "kirimin 628xxx [pesan]"
        $sendPatterns = [
            // "chat/kirim/dm [nomor] bilang/kasih tau/sampaikan [pesan]"
            '/^(?:tolong\s+)?(?:chat|kirim(?:in)?|dm|hubungi|kontak|sampai(?:kan|in)|bilang(?:in)?)\s+(?:ke\s+)?(\+?[\d\s\-]+)\s+(?:bilang(?:in)?|kasih\s*tau|sampai(?:kan|in)|pesan(?:in)?)\s+["\']?(.+?)["\']?\s*$/iu',
            // "chat/kirim/dm [nomor] "[pesan]""
            '/^(?:tolong\s+)?(?:chat|kirim(?:in)?|dm|hubungi|kontak)\s+(?:ke\s+)?(\+?[\d\s\-]+)\s+["\'](.+?)["\']\s*$/iu',
            // "chat/kirim/dm [nomor] [pesan]" (fallback - everything after number is the message)
            '/^(?:tolong\s+)?(?:chat|kirim(?:in)?|dm|hubungi|kontak)\s+(?:ke\s+)?(\+?[\d\s\-]+)\s+(.+)$/iu',
            // "bilang ke [nomor] [pesan]"
            '/^(?:tolong\s+)?(?:bilang(?:in)?|sampai(?:kan|in))\s+(?:ke\s+)?(\+?[\d\s\-]+)\s+(.+)$/iu',
        ];

        foreach ($sendPatterns as $pattern) {
            if (preg_match($pattern, $cleaned, $matches)) {
                $phone = preg_replace('/[\s\-]/', '', $matches[1]); // Remove spaces/dashes
                $phone = preg_replace('/^\+/', '', $phone); // Remove leading +
                // Convert 08xxx to 628xxx
                if (str_starts_with($phone, '0')) {
                    $phone = '62' . substr($phone, 1);
                }
                $message = trim($matches[2]);

                if (!empty($phone) && !empty($message) && strlen($phone) >= 10) {
                    return [
                        'type' => 'send_message',
                        'phone' => $phone,
                        'message' => $message,
                    ];
                }
            }
        }

        // --- Command: Send message to nickname ---
        // "chat [nickname] bilang/kasih tau [pesan]"
        // "chat princess bilang kangen"
        $nickPatterns = [
            '/^(?:tolong\s+)?(?:chat|kirim(?:in)?|dm|hubungi)\s+(?:ke\s+)?([a-zA-Z\s]+?)\s+(?:bilang(?:in)?|kasih\s*tau|sampai(?:kan|in)|pesan(?:in)?)\s+["\']?(.+?)["\']?\s*$/iu',
            '/^(?:tolong\s+)?(?:chat|kirim(?:in)?|dm|hubungi)\s+(?:ke\s+)?([a-zA-Z\s]+?)\s+["\'](.+?)["\']\s*$/iu',
            '/^(?:tolong\s+)?(?:bilang(?:in)?|sampai(?:kan|in))\s+(?:ke\s+)?([a-zA-Z\s]+?)\s+(.+)$/iu',
        ];

        foreach ($nickPatterns as $pattern) {
            if (preg_match($pattern, $cleaned, $matches)) {
                $nickname = trim($matches[1]);
                $message = trim($matches[2]);

                if (!empty($nickname) && !empty($message)) {
                    // Lookup nickname in members
                    $member = $this->findMemberByNickname($nickname);
                    if ($member) {
                        $phone = $member['lid'] ?: $member['phone_number'];
                        return [
                            'type' => 'send_message',
                            'phone' => $phone,
                            'message' => $message,
                            'nickname' => $member['nickname'],
                        ];
                    }
                }
            }
        }

        // --- Command: Broadcast ---
        // "broadcast [pesan]"
        if (preg_match('/^(?:tolong\s+)?broadcast\s+(.+)$/iu', $cleaned, $matches)) {
            return [
                'type' => 'broadcast',
                'message' => trim($matches[1]),
            ];
        }

        return null;
    }

    /**
     * Execute send message command
     */
    private function executeSendMessage(array $command): array
    {
        $phone = $command['phone'];
        $message = $command['message'];
        $nickname = $command['nickname'] ?? null;

        // Build chat ID
        $chatId = $phone . '@c.us';
        // If it looks like a LID (not starting with country code pattern)
        if (strlen($phone) > 15 || (!str_starts_with($phone, '62') && !str_starts_with($phone, '1') && !str_starts_with($phone, '44'))) {
            $chatId = $phone . '@lid';
        }

        Logger::info("Sending message by command", [
            'to' => $chatId,
            'message_preview' => mb_substr($message, 0, 50),
        ]);

        $result = $this->waha->sendMessage($chatId, $message);

        if ($result !== null) {
            $target = $nickname ? $nickname : $phone;
            return [
                'is_command' => true,
                'response' => "udah gue sampaiin ke {$target} ya 👍",
            ];
        }

        return [
            'is_command' => true,
            'response' => "waduh gagal kirim nih 😅 coba lagi ntar ya",
        ];
    }

    /**
     * Execute broadcast command (owner only)
     */
    private function executeBroadcast(array $command): array
    {
        $message = $command['message'];
        $members = $this->db->fetchAll(
            "SELECT phone_number, lid, nickname FROM members WHERE is_active = 1 AND role != 'bot'"
        );

        $sent = 0;
        $failed = 0;

        foreach ($members as $m) {
            $phone = $m['lid'] ?: $m['phone_number'];
            $chatId = $m['lid'] ? ($m['lid'] . '@lid') : ($m['phone_number'] . '@c.us');

            $result = $this->waha->sendMessage($chatId, $message);
            if ($result !== null) {
                $sent++;
            } else {
                $failed++;
            }

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 second
        }

        return [
            'is_command' => true,
            'response' => "broadcast selesai! terkirim ke {$sent} orang" . ($failed > 0 ? ", gagal {$failed}" : "") . " 📢",
        ];
    }

    /**
     * Get member role by phone/LID
     */
    private function getMemberRole(string $identifier): ?string
    {
        if (empty($identifier)) {
            return null;
        }

        $member = $this->db->fetchOne(
            "SELECT role FROM members WHERE (lid = ? OR phone_number = ?) AND is_active = 1",
            [$identifier, $identifier]
        );

        return $member['role'] ?? null;
    }

    /**
     * Find member by nickname (case-insensitive)
     */
    private function findMemberByNickname(string $nickname): ?array
    {
        return $this->db->fetchOne(
            "SELECT phone_number, lid, nickname, role FROM members WHERE LOWER(nickname) = LOWER(?) AND is_active = 1",
            [trim($nickname)]
        );
    }
}
