<?php

namespace BotWA;

class GameEngine
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Check if message is a game command and handle it
     */
    public function handleGameCommand(string $text, string $chatId, string $senderPhone): ?string
    {
        $text = strtolower(trim($text));

        // Check for active game session
        $activeGame = $this->getActiveGame($chatId);

        if ($activeGame) {
            return $this->continueGame($activeGame, $text, $senderPhone);
        }

        return null; // No game action needed
    }

    /**
     * Start a new game session
     */
    public function startGame(string $chatId, string $gameType, string $startedBy): int
    {
        // End any active games first
        $this->db->update(
            'game_sessions',
            ['is_active' => 0],
            'chat_id = ? AND is_active = 1',
            [$chatId]
        );

        return $this->db->insert('game_sessions', [
            'chat_id' => $chatId,
            'game_type' => $gameType,
            'game_state' => json_encode(['round' => 1, 'scores' => []]),
            'is_active' => 1,
            'started_by' => $startedBy,
        ]);
    }

    /**
     * Get active game for a chat
     */
    public function getActiveGame(string $chatId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM game_sessions WHERE chat_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1",
            [$chatId]
        );
    }

    /**
     * Continue an active game
     */
    private function continueGame(array $game, string $text, string $senderPhone): ?string
    {
        // Check for game end commands
        if (in_array($text, ['stop game', 'end game', 'udahan', 'stop', 'selesai'])) {
            $this->endGame($game['id']);
            return null; // Let AI handle the farewell
        }

        // Update game state
        $state = json_decode($game['game_state'], true) ?? [];
        $state['last_answer'] = $text;
        $state['last_player'] = $senderPhone;

        $this->db->update(
            'game_sessions',
            ['game_state' => json_encode($state)],
            'id = ?',
            [$game['id']]
        );

        return null; // Let AI handle the game flow
    }

    /**
     * End a game session
     */
    public function endGame(int $gameId): bool
    {
        return $this->db->update(
            'game_sessions',
            ['is_active' => 0],
            'id = ?',
            [$gameId]
        ) >= 0;
    }

    /**
     * Get game history for a chat
     */
    public function getHistory(string $chatId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM game_sessions WHERE chat_id = ? ORDER BY created_at DESC LIMIT ?",
            [$chatId, $limit]
        );
    }
}
