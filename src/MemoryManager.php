<?php

namespace BotWA;

class MemoryManager
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Add message to conversation history
     */
    public function addToHistory(string $chatId, string $role, string $senderPhone, string $senderNickname, string $content): void
    {
        try {
            $this->db->insert('conversation_history', [
                'chat_id' => $chatId,
                'role' => $role,
                'sender_phone' => $senderPhone,
                'sender_nickname' => $senderNickname,
                'content' => $content,
            ]);
        } catch (\Exception $e) {
            Logger::error("Failed to add to history: " . $e->getMessage());
        }
    }

    /**
     * Get conversation history for a chat
     */
    public function getHistory(string $chatId, int $limit = 20): array
    {
        try {
            // Get last N messages, then reverse to chronological order
            $rows = $this->db->fetchAll(
                "SELECT role, sender_phone, sender_nickname, content, created_at 
                 FROM conversation_history 
                 WHERE chat_id = ? 
                 ORDER BY id DESC 
                 LIMIT ?",
                [$chatId, $limit]
            );
            return array_reverse($rows);
        } catch (\Exception $e) {
            Logger::error("Failed to get history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get relevant memories based on message content
     */
    public function getRelevantMemories(string $messageText, int $limit = 10): array
    {
        try {
            // Get high-importance memories first, then keyword-matched ones
            $memories = $this->db->fetchAll(
                "SELECT content, memory_type, subject, importance FROM memory 
                 WHERE is_active = 1 
                 AND (expires_at IS NULL OR expires_at > NOW())
                 ORDER BY importance DESC, updated_at DESC 
                 LIMIT ?",
                [$limit]
            );

            return $memories;
        } catch (\Exception $e) {
            Logger::error("Failed to get memories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add a new memory
     */
    public function addMemory(string $type, string $subject, string $content, int $importance = 5): int
    {
        return $this->db->insert('memory', [
            'memory_type' => $type,
            'subject' => $subject,
            'content' => $content,
            'importance' => $importance,
            'is_active' => 1,
        ]);
    }

    /**
     * Update existing memory
     */
    public function updateMemory(int $id, array $data): bool
    {
        return $this->db->update('memory', $data, 'id = ?', [$id]) >= 0;
    }

    /**
     * Delete a memory
     */
    public function deleteMemory(int $id): bool
    {
        return $this->db->delete('memory', 'id = ?', [$id]) > 0;
    }

    /**
     * Get all memories (for admin panel)
     */
    public function getAll(string $type = ''): array
    {
        if (!empty($type)) {
            return $this->db->fetchAll(
                "SELECT * FROM memory WHERE memory_type = ? ORDER BY importance DESC, updated_at DESC",
                [$type]
            );
        }
        return $this->db->fetchAll(
            "SELECT * FROM memory ORDER BY importance DESC, updated_at DESC"
        );
    }

    /**
     * Auto-extract important facts from conversation
     * This is a simple keyword-based extraction - can be enhanced with AI later
     */
    public function autoExtractFacts(array $messageData, string $botResponse): void
    {
        $text = strtolower($messageData['text']);

        // Detect birthday mentions
        if (preg_match('/ultah|ulang tahun|birthday|lahir/', $text)) {
            $this->addMemory(
                'event',
                $messageData['senderPhone'],
                "Ada pembicaraan tentang ulang tahun dari {$messageData['senderPhone']}: {$messageData['text']}",
                7
            );
        }

        // Detect preference mentions
        if (preg_match('/suka|favorit|favourite|favorite|hobi|hobby/', $text)) {
            $this->addMemory(
                'preference',
                $messageData['senderPhone'],
                "Preferensi dari {$messageData['senderPhone']}: {$messageData['text']}",
                6
            );
        }
    }

    /**
     * Clean old conversation history (keep last N per chat)
     */
    public function cleanOldHistory(int $keepPerChat = 100): int
    {
        try {
            // Get distinct chat IDs
            $chats = $this->db->fetchAll(
                "SELECT DISTINCT chat_id FROM conversation_history"
            );

            $totalDeleted = 0;
            foreach ($chats as $chat) {
                $chatId = $chat['chat_id'];
                // Get the ID threshold
                $threshold = $this->db->fetchOne(
                    "SELECT id FROM conversation_history 
                     WHERE chat_id = ? 
                     ORDER BY id DESC 
                     LIMIT 1 OFFSET ?",
                    [$chatId, $keepPerChat]
                );

                if ($threshold) {
                    $deleted = $this->db->delete(
                        'conversation_history',
                        'chat_id = ? AND id < ?',
                        [$chatId, $threshold['id']]
                    );
                    $totalDeleted += $deleted;
                }
            }

            return $totalDeleted;
        } catch (\Exception $e) {
            Logger::error("Failed to clean history: " . $e->getMessage());
            return 0;
        }
    }
}
