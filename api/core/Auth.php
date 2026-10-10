<?php

require_once __DIR__ . '/../config/database.php';

class Auth {
    /**
     * Start a secure session if not already started.
     */
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session cookie parameters
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']), // True if HTTPS
                'httponly' => true,                   // Prevent JS access
                'samesite' => 'Lax'                   // CSRF mitigation
            ]);
            session_start();
        }
    }

    /**
     * Authenticate a user by email and password.
     */
    public static function login(string $email, string $password): array {
        self::startSession();

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, password_hash, role, status, force_password_change FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is disabled'];
        }

        if (password_verify($password, $user['password_hash'])) {
            // Prevent session fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['force_password_change'] = (bool) $user['force_password_change'];

            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'role' => $user['role'],
                    'force_password_change' => (bool) $user['force_password_change']
                ]
            ];
        }

        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    /**
     * Log the current user out.
     */
    public static function logout(): void {
        self::startSession();
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Generate a CSRF token.
     */
    public static function generateCsrfToken(): string {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify a CSRF token.
     */
    public static function verifyCsrfToken(?string $token): bool {
        self::startSession();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Check if a user is currently logged in.
     */
    public static function isLoggedIn(): bool {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    /**
     * Get the current logged in user's ID.
     */
    public static function getUserId(): ?int {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }
}
