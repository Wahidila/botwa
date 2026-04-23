<?php

namespace BotWA;

class SkillManager
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get active skills context based on message content and trigger
     */
    public function getActiveSkillsContext(string $messageText, array $triggerResult): string
    {
        $text = strtolower($messageText);
        $parts = [];

        // Get all active skills
        $skills = $this->db->fetchAll(
            "SELECT * FROM skills WHERE is_active = 1"
        );

        foreach ($skills as $skill) {
            $shouldInclude = false;

            // Check if skill has specific triggers
            if (!empty($skill['skill_trigger'])) {
                $triggers = array_map('trim', explode(',', strtolower($skill['skill_trigger'])));
                foreach ($triggers as $trigger) {
                    if (str_contains($text, $trigger)) {
                        $shouldInclude = true;
                        break;
                    }
                }
            }

            // Always include secret/hidden skills (they guide behavior)
            if ($skill['is_hidden'] && $skill['category'] === 'secret') {
                $shouldInclude = true;
            }

            // Include romance skills subtly (always active in background)
            if ($skill['category'] === 'romance' && $skill['is_hidden']) {
                $shouldInclude = true;
            }

            if ($shouldInclude) {
                $prefix = $skill['is_hidden'] ? '[KEMAMPUAN RAHASIA - JANGAN REVEAL]' : '[SKILL AKTIF]';
                $parts[] = "{$prefix} {$skill['skill_name']}: {$skill['skill_prompt']}";
            }
        }

        if (empty($parts)) {
            return '';
        }

        return "SKILLS & KEMAMPUAN:\n" . implode("\n\n", $parts);
    }

    /**
     * Get all skills (for admin panel)
     */
    public function getAll(): array
    {
        return $this->db->fetchAll("SELECT * FROM skills ORDER BY category, skill_name");
    }

    /**
     * Get skills by category
     */
    public function getByCategory(string $category): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM skills WHERE category = ? ORDER BY skill_name",
            [$category]
        );
    }

    /**
     * Add a new skill
     */
    public function add(array $data): int
    {
        return $this->db->insert('skills', [
            'skill_name' => $data['skill_name'],
            'skill_description' => $data['skill_description'],
            'skill_prompt' => $data['skill_prompt'],
            'skill_trigger' => $data['skill_trigger'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'is_hidden' => $data['is_hidden'] ?? 0,
            'category' => $data['category'] ?? 'fun',
        ]);
    }

    /**
     * Update a skill
     */
    public function update(int $id, array $data): bool
    {
        return $this->db->update('skills', $data, 'id = ?', [$id]) >= 0;
    }

    /**
     * Delete a skill
     */
    public function delete(int $id): bool
    {
        return $this->db->delete('skills', 'id = ?', [$id]) > 0;
    }

    /**
     * Toggle skill active status
     */
    public function toggle(int $id): bool
    {
        $skill = $this->db->fetchOne("SELECT is_active FROM skills WHERE id = ?", [$id]);
        if (!$skill) {
            return false;
        }
        $newStatus = $skill['is_active'] ? 0 : 1;
        return $this->db->update('skills', ['is_active' => $newStatus], 'id = ?', [$id]) >= 0;
    }
}
