<?php

namespace BotWA;

class Logger
{
    private static string $logDir = '';

    public static function init(string $logDir): void
    {
        self::$logDir = rtrim($logDir, '/\\');
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if ($_ENV['DEBUG_MODE'] ?? false) {
            self::write('DEBUG', $message, $context);
        }
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (empty(self::$logDir)) {
            self::$logDir = dirname(__DIR__) . '/logs';
            if (!is_dir(self::$logDir)) {
                @mkdir(self::$logDir, 0755, true);
            }
        }

        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');
        $file = self::$logDir . "/bot-{$date}.log";

        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$time}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
