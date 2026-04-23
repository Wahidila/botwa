<?php

namespace BotWA;

class AdminAuth
{
    /**
     * Start session if not started
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = (int) ($_ENV['ADMIN_SESSION_LIFETIME'] ?? 3600);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    /**
     * Attempt login
     */
    public static function login(string $username, string $password): bool
    {
        try {
            $db = Database::getInstance();
            $user = $db->fetchOne(
                "SELECT * FROM admin_users WHERE username = ?",
                [$username]
            );

            if (!$user) {
                Logger::warning("Login failed: user not found", ['username' => $username]);
                return false;
            }

            if (!password_verify($password, $user['password_hash'])) {
                Logger::warning("Login failed: wrong password", ['username' => $username]);
                return false;
            }

            // Update last login
            $db->update('admin_users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

            // Set session
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_login_time'] = time();

            // Regenerate session ID for security
            session_regenerate_id(true);

            Logger::info("Admin login successful", ['username' => $username]);
            return true;
        } catch (\Exception $e) {
            Logger::error("Login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool
    {
        self::startSession();
        return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    }

    /**
     * Require authentication (redirect to login if not logged in)
     */
    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Logout
     */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Get current admin username
     */
    public static function getUsername(): string
    {
        return $_SESSION['admin_username'] ?? 'Unknown';
    }

    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Change password
     */
    public static function changePassword(int $userId, string $newPassword): bool
    {
        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db = Database::getInstance();
            return $db->update('admin_users', ['password_hash' => $hash], 'id = ?', [$userId]) >= 0;
        } catch (\Exception $e) {
            Logger::error("Change password failed: " . $e->getMessage());
            return false;
        }
    }
}
