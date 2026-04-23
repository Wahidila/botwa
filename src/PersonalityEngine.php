<?php

namespace BotWA;

class PersonalityEngine
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Build the complete system prompt from personality components + member context
     * $groupParticipants = real-time data from WAHA API (nullable)
     */
    public function buildSystemPrompt(array $messageData, ?array $groupParticipants = null): string
    {
        $parts = [];

        // 1. Load all active personality components (sorted)
        $personas = $this->db->fetchAll(
            "SELECT persona_key, persona_value FROM personality WHERE is_active = 1 ORDER BY sort_order ASC"
        );

        foreach ($personas as $p) {
            $parts[] = $p['persona_value'];
        }

        // 2. Add member context (registered nicknames)
        $memberContext = $this->buildMemberContext();
        if (!empty($memberContext)) {
            $parts[] = $memberContext;
        }

        // 3. Add real-time group presence (who's actually in the group right now)
        $groupContext = $this->buildGroupPresenceContext($groupParticipants);
        if (!empty($groupContext)) {
            $parts[] = $groupContext;
        }

        // 4. Add current context info
        $parts[] = $this->buildCurrentContext($messageData);

        // 5. Add web search capability notice
        $firecrawlEnabled = (bool) Config::get('firecrawl_enabled', false);
        $firecrawlKey = Config::get('firecrawl_api_key', '');
        if ($firecrawlEnabled && !empty($firecrawlKey)) {
            $parts[] = implode("\n", [
                "KEMAMPUAN PENCARIAN WEB:",
                "- Kamu BISA mencari informasi terbaru di internet.",
                "- Kalau ada hasil pencarian web yang diberikan di atas, GUNAKAN data itu untuk menjawab.",
                "- Rangkum hasil pencarian dengan gaya bahasa kamu sendiri (casual, Gen Z).",
                "- Jangan bilang kamu tidak bisa search internet - kamu BISA.",
                "- Sebutkan sumber jika relevan tapi jangan tampilkan URL mentah yang panjang.",
            ]);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Build member nickname mapping context
     */
    private function buildMemberContext(): string
    {
        $members = $this->db->fetchAll(
            "SELECT phone_number, nickname, role FROM members WHERE is_active = 1"
        );

        if (empty($members)) {
            return '';
        }

        $lines = ["DAFTAR ORANG YANG KAMU KENAL:"];
        foreach ($members as $m) {
            $roleLabel = match ($m['role']) {
                'owner' => '(ini bos kamu)',
                'princess' => '(ini yang spesial)',
                'bot' => '(ini kamu sendiri)',
                default => '',
            };
            $lines[] = "- {$m['nickname']} {$roleLabel}";
        }

        $lines[] = "\nATURAN KETAT SOAL NOMOR TELEPON:";
        $lines[] = "- JANGAN PERNAH menyebut, menampilkan, atau membocorkan nomor telepon siapapun di chat.";
        $lines[] = "- Kalau ditanya nomor seseorang, tolak dengan halus. Contoh: 'waduh gue ga hafal nomornya' atau 'coba tanya langsung aja'.";
        $lines[] = "- SELALU panggil orang dengan NICKNAME mereka, BUKAN nomor telepon.";
        $lines[] = "- Kamu TIDAK TAHU nomor telepon siapapun.";

        return implode("\n", $lines);
    }

    /**
     * Build real-time group presence context
     * Tells the AI exactly who is currently in the group
     */
    private function buildGroupPresenceContext(?array $participants): string
    {
        if ($participants === null || empty($participants)) {
            return '';
        }

        // Load registered members - build lookup maps by LID and by phone
        $members = $this->db->fetchAll(
            "SELECT phone_number, lid, nickname, role FROM members WHERE is_active = 1"
        );

        // Build lookup: lid -> member, phone -> member
        $lidMap = [];
        $phoneMap = [];
        $allMembers = [];
        foreach ($members as $m) {
            $allMembers[] = $m;
            if (!empty($m['lid'])) {
                $lid = preg_replace('/\D/', '', $m['lid']);
                $lidMap[$lid] = $m;
            }
            if (!empty($m['phone_number'])) {
                $phone = preg_replace('/\D/', '', $m['phone_number']);
                $phoneMap[$phone] = $m;
            }
        }

        $inGroup = [];
        $matchedNicknames = [];
        $unknownCount = 0;

        foreach ($participants as $p) {
            $rawId = $p['id'] ?? '';
            $cleanId = preg_replace('/@.*$/', '', $rawId);
            $cleanId = preg_replace('/\D/', '', $cleanId);
            if (empty($cleanId)) continue;

            $matched = null;

            // Try match by LID
            if (isset($lidMap[$cleanId])) {
                $matched = $lidMap[$cleanId];
            }
            // Try match by phone
            if (!$matched && isset($phoneMap[$cleanId])) {
                $matched = $phoneMap[$cleanId];
            }

            if ($matched) {
                $nick = $matched['nickname'];
                $inGroup[] = "- {$nick} (HADIR di grup)";
                $matchedNicknames[] = $nick;
            } else {
                $unknownCount++;
            }
        }

        if ($unknownCount > 0) {
            $inGroup[] = "- {$unknownCount} orang lain yang belum kamu kenal (HADIR di grup)";
        }

        // Check registered members who are NOT in the group
        $notInGroup = [];
        foreach ($allMembers as $m) {
            if (!in_array($m['nickname'], $matchedNicknames)) {
                $notInGroup[] = "- {$m['nickname']} (BELUM ADA di grup / sudah keluar)";
            }
        }

        $lines = ["SIAPA YANG ADA DI GRUP SAAT INI (data real-time):"];
        $lines = array_merge($lines, $inGroup);

        if (!empty($notInGroup)) {
            $lines[] = "\nYANG BELUM ADA / TIDAK ADA DI GRUP:";
            $lines = array_merge($lines, $notInGroup);
        }

        $lines[] = "\nPENTING:";
        $lines[] = "- Kalau ditanya apakah seseorang ada di grup, jawab berdasarkan data di atas. JANGAN mengarang.";
        $lines[] = "- JANGAN PERNAH menyebut nomor telepon siapapun. Kamu TIDAK TAHU nomor telepon.";

        return implode("\n", $lines);
    }

    /**
     * Build current context (time, day, etc)
     */
    private function buildCurrentContext(array $messageData): string
    {
        $now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $hour = (int) $now->format('H');
        $dayName = $this->getDayNameIndo($now->format('l'));

        $timeContext = match (true) {
            $hour >= 5 && $hour < 11 => 'pagi',
            $hour >= 11 && $hour < 15 => 'siang',
            $hour >= 15 && $hour < 18 => 'sore',
            default => 'malam',
        };

        $senderPhone = $messageData['senderPhone'] ?? '';
        $senderNick = $this->getNickname($senderPhone);

        $lines = [
            "KONTEKS SAAT INI:",
            "- Waktu: {$now->format('H:i')} WIB, hari {$dayName} {$timeContext}",
            "- Yang barusan chat: {$senderNick}",
            "- Ini adalah grup WhatsApp",
        ];

        // Add time-appropriate behavior hints
        if ($hour >= 23 || $hour < 5) {
            $lines[] = "- Ini sudah larut malam, bisa comment soal begadang atau ngantuk";
        } elseif ($hour >= 5 && $hour < 8) {
            $lines[] = "- Masih pagi banget, bisa sapaan pagi yang fresh";
        }

        return implode("\n", $lines);
    }

    /**
     * Get nickname for a phone number or LID
     */
    private function getNickname(string $identifier): string
    {
        if (empty($identifier)) {
            return 'seseorang';
        }

        // Normalize: strip @xxx suffix, keep digits
        $identifier = preg_replace('/@.*$/', '', $identifier);
        $identifier = preg_replace('/\D/', '', $identifier);

        // Try match by LID
        $member = $this->db->fetchOne(
            "SELECT nickname FROM members WHERE lid = ? AND is_active = 1",
            [$identifier]
        );
        if ($member) {
            return $member['nickname'];
        }

        // Try match by phone
        $member = $this->db->fetchOne(
            "SELECT nickname FROM members WHERE phone_number = ? AND is_active = 1",
            [$identifier]
        );
        if ($member) {
            return $member['nickname'];
        }

        return 'seseorang';
    }

    /**
     * Get Indonesian day name
     */
    private function getDayNameIndo(string $englishDay): string
    {
        return match (strtolower($englishDay)) {
            'monday' => 'Senin',
            'tuesday' => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday' => 'Kamis',
            'friday' => 'Jumat',
            'saturday' => 'Sabtu',
            'sunday' => 'Minggu',
            default => $englishDay,
        };
    }

    /**
     * Get all personality components (for admin panel)
     */
    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM personality ORDER BY sort_order ASC"
        );
    }

    /**
     * Update a personality component
     */
    public function update(int $id, string $value, bool $isActive): bool
    {
        return $this->db->update(
            'personality',
            ['persona_value' => $value, 'is_active' => $isActive ? 1 : 0],
            'id = ?',
            [$id]
        ) >= 0;
    }

    /**
     * Add a new personality component
     */
    public function add(string $key, string $value, string $description = '', int $sortOrder = 99): int
    {
        return $this->db->insert('personality', [
            'persona_key' => $key,
            'persona_value' => $value,
            'description' => $description,
            'sort_order' => $sortOrder,
            'is_active' => 1,
        ]);
    }

    /**
     * Delete a personality component
     */
    public function delete(int $id): bool
    {
        return $this->db->delete('personality', 'id = ?', [$id]) > 0;
    }
}
