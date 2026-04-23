<?php

namespace BotWA;

class TriggerManager
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Check if a message triggers the bot
     */
    public function check(string $messageText): array
    {
        $text = strtolower(trim($messageText));

        $triggers = $this->db->fetchAll(
            "SELECT * FROM triggers WHERE is_active = 1 ORDER BY priority DESC"
        );

        foreach ($triggers as $trigger) {
            $word = strtolower($trigger['trigger_word']);
            $matched = false;

            switch ($trigger['trigger_type']) {
                case 'exact':
                    $matched = ($text === $word);
                    break;

                case 'contains':
                    $matched = str_contains($text, $word);
                    break;

                case 'startswith':
                    $matched = str_starts_with($text, $word);
                    break;

                case 'regex':
                    $matched = (bool) preg_match($word, $text);
                    break;
            }

            if ($matched) {
                Logger::debug("Trigger matched: '{$trigger['trigger_word']}' ({$trigger['trigger_type']})");
                return [
                    'triggered' => true,
                    'trigger' => $trigger,
                    'response_mode' => $trigger['response_mode'],
                    'cleaned_text' => $this->cleanTriggerFromText($text, $word),
                ];
            }
        }

        return ['triggered' => false];
    }

    /**
     * Remove trigger word from message text to get the actual question/command
     */
    private function cleanTriggerFromText(string $text, string $triggerWord): string
    {
        // Remove the trigger word and clean up
        $cleaned = str_ireplace($triggerWord, '', $text);
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));
        // Remove leading punctuation/comma
        $cleaned = ltrim($cleaned, ' ,!.');
        return trim($cleaned);
    }

    /**
     * Get all triggers (for admin panel)
     */
    public function getAll(): array
    {
        return $this->db->fetchAll("SELECT * FROM triggers ORDER BY priority DESC, trigger_word");
    }

    /**
     * Add a new trigger
     */
    public function add(string $word, string $type = 'contains', string $responseMode = 'ai', int $priority = 5): int
    {
        return $this->db->insert('triggers', [
            'trigger_word' => $word,
            'trigger_type' => $type,
            'response_mode' => $responseMode,
            'priority' => $priority,
            'is_active' => 1,
        ]);
    }

    /**
     * Update a trigger
     */
    public function update(int $id, array $data): bool
    {
        return $this->db->update('triggers', $data, 'id = ?', [$id]) >= 0;
    }

    /**
     * Delete a trigger
     */
    public function delete(int $id): bool
    {
        return $this->db->delete('triggers', 'id = ?', [$id]) > 0;
    }

    /**
     * Toggle trigger active status
     */
    public function toggle(int $id): bool
    {
        $trigger = $this->db->fetchOne("SELECT is_active FROM triggers WHERE id = ?", [$id]);
        if (!$trigger) {
            return false;
        }
        $newStatus = $trigger['is_active'] ? 0 : 1;
        return $this->db->update('triggers', ['is_active' => $newStatus], 'id = ?', [$id]) >= 0;
    }
}
