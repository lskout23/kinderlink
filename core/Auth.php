<?php
class Auth {
    public static function login(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['name']      = $user['name'];
        $_SESSION['email']     = $user['email'] ?? '';
        $_SESSION['role']      = $user['role'];
        $_SESSION['logged_in'] = true;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function check(): bool {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
    }

    public static function requireRole(string ...$roles): void {
        self::requireLogin();
        if (!in_array($_SESSION['role'], $roles, true)) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
    }

    public static function user(): array {
        return [
            'id'       => $_SESSION['user_id']   ?? 0,
            'username' => $_SESSION['username']  ?? '',
            'name'     => $_SESSION['name']      ?? '',
            'email'    => $_SESSION['email']     ?? '',
            'role'     => $_SESSION['role']      ?? '',
        ];
    }

    public static function isAdmin(): bool {
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    public static function isTeacher(): bool {
        return ($_SESSION['role'] ?? '') === 'teacher';
    }

    public static function isParent(): bool {
        return ($_SESSION['role'] ?? '') === 'parent';
    }

    /**
     * Hash a plaintext password
     */
    public static function hashPassword(string $plain): string {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify a plaintext password against a stored hash
     */
    public static function verifyPassword(string $plain, string $hash): bool {
        return password_verify($plain, $hash);
    }

    /**
     * Username policy: 4-50 chars, latin letters, numbers, at, dot, underscore, hyphen.
     */
    public static function isValidUsername(string $username): bool {
        return (bool)preg_match('/^[A-Za-z0-9@._-]{4,50}$/', $username);
    }

    /**
     * Password policy: >=10 chars, at least lower, upper, digit, symbol, no spaces.
     * Returns empty string when valid, otherwise a human-readable error.
     */
    public static function validatePasswordStrength(string $password): string {
        if (strlen($password) < 10) {
            return 'Ο κωδικός πρέπει να έχει τουλάχιστον 10 χαρακτήρες.';
        }
        if (preg_match('/\s/', $password)) {
            return 'Ο κωδικός δεν πρέπει να περιέχει κενά.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Ο κωδικός πρέπει να περιέχει τουλάχιστον ένα μικρό γράμμα.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Ο κωδικός πρέπει να περιέχει τουλάχιστον ένα κεφαλαίο γράμμα.';
        }
        if (!preg_match('/\d/', $password)) {
            return 'Ο κωδικός πρέπει να περιέχει τουλάχιστον έναν αριθμό.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Ο κωδικός πρέπει να περιέχει τουλάχιστον ένα σύμβολο.';
        }

        return '';
    }
}
