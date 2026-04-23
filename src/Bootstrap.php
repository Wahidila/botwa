<?php

namespace BotWA;

class Bootstrap
{
    private static bool $initialized = false;

    /**
     * Initialize the application
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Define base path
        $basePath = dirname(__DIR__);

        // Load Composer autoloader
        $autoloader = $basePath . '/vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        } else {
            // Manual autoloader fallback (if composer not available)
            spl_autoload_register(function ($class) use ($basePath) {
                $prefix = 'BotWA\\';
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }
                $relativeClass = substr($class, $len);
                $file = $basePath . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            });
        }

        // Load .env file
        self::loadEnv($basePath . '/.env');

        // Initialize logger
        Logger::init($basePath . '/logs');

        // Set timezone
        date_default_timezone_set('Asia/Jakarta');

        // Error handling
        set_error_handler(function ($severity, $message, $file, $line) {
            Logger::error("PHP Error: {$message}", [
                'file' => $file,
                'line' => $line,
                'severity' => $severity,
            ]);
            return false;
        });

        set_exception_handler(function (\Throwable $e) {
            Logger::error("Uncaught Exception: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });

        self::$initialized = true;
    }

    /**
     * Simple .env file loader
     */
    private static function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if (str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }
            // Parse KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Remove surrounding quotes
                $value = trim($value, '"\'');
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}
