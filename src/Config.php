<?php

namespace BotWA;

class Config
{
    private static array $cache = [];

    /**
     * Get a setting value from database
     */
    public static function get(string $key, $default = null)
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $db = Database::getInstance();
            $row = $db->fetchOne(
                "SELECT setting_value, setting_type FROM settings WHERE setting_key = ?",
                [$key]
            );

            if (!$row) {
                return $default;
            }

            $value = self::castValue($row['setting_value'], $row['setting_type']);
            self::$cache[$key] = $value;
            return $value;
        } catch (\Exception $e) {
            Logger::error("Config::get failed for key '{$key}': " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Set a setting value in database
     */
    public static function set(string $key, $value): bool
    {
        try {
            $db = Database::getInstance();
            $db->query(
                "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                [(string) $value, $key]
            );
            self::$cache[$key] = $value;
            return true;
        } catch (\Exception $e) {
            Logger::error("Config::set failed for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings by category
     */
    public static function getByCategory(string $category): array
    {
        try {
            $db = Database::getInstance();
            return $db->fetchAll(
                "SELECT * FROM settings WHERE category = ? ORDER BY id",
                [$category]
            );
        } catch (\Exception $e) {
            Logger::error("Config::getByCategory failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all settings as key-value pairs
     */
    public static function all(): array
    {
        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll("SELECT setting_key, setting_value, setting_type FROM settings");
            $result = [];
            foreach ($rows as $row) {
                $result[$row['setting_key']] = self::castValue($row['setting_value'], $row['setting_type']);
            }
            return $result;
        } catch (\Exception $e) {
            Logger::error("Config::all failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear config cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Cast value based on type
     */
    private static function castValue(?string $value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : 0,
            'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on']),
            'json' => json_decode($value, true) ?? [],
            default => $value,
        };
    }
}
