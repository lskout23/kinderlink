<?php
class Auth {
    /** Στοιχεία χρήστη όταν η ταυτοποίηση έγινε με Bearer token (native app). */
    private static ?array $tokenUser = null;
    /** id της εγγραφής api_tokens που χρησιμοποιήθηκε στο τρέχον request. */
    private static int $tokenId = 0;

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
        if (self::$tokenUser !== null) {
            return true;
        }
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function requireLogin(): void {
        if (self::check()) {
            return;
        }

        // Οι API clients παίρνουν JSON 401 αντί για redirect στη σελίδα σύνδεσης.
        if (self::bearerToken() !== '') {
            self::jsonError('Μη έγκυρο ή ληγμένο token.', 401);
        }

        header('Location: ' . BASE_URL . '/');
        exit;
    }

    public static function requireRole(string ...$roles): void {
        self::requireLogin();
        if (!in_array(self::user()['role'], $roles, true)) {
            if (self::$tokenUser !== null) {
                self::jsonError('Δεν έχετε δικαίωμα για αυτή την ενέργεια.', 403);
            }
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
    }

    public static function user(): array {
        if (self::$tokenUser !== null) {
            return self::$tokenUser;
        }
        return [
            'id'       => $_SESSION['user_id']   ?? 0,
            'username' => $_SESSION['username']  ?? '',
            'name'     => $_SESSION['name']      ?? '',
            'email'    => $_SESSION['email']     ?? '',
            'role'     => $_SESSION['role']      ?? '',
        ];
    }

    public static function userId(): int {
        return (int)(self::user()['id'] ?? 0);
    }

    public static function isAdmin(): bool {
        return self::user()['role'] === 'admin';
    }

    public static function isTeacher(): bool {
        return self::user()['role'] === 'teacher';
    }

    public static function isParent(): bool {
        return self::user()['role'] === 'parent';
    }

    // ── Bearer token authentication (Android/iOS app) ──────────────────

    /** True όταν το τρέχον request ταυτοποιήθηκε με token και όχι με session cookie. */
    public static function isTokenAuth(): bool {
        return self::$tokenUser !== null;
    }

    public static function currentTokenId(): int {
        return self::$tokenId;
    }

    /**
     * Διαβάζει το Authorization: Bearer header και ταυτοποιεί τον χρήστη.
     * Δεν αγγίζει το session: η web εφαρμογή συνεχίζει να λειτουργεί όπως πριν.
     */
    public static function authenticateBearerToken(): void {
        $raw = self::bearerToken();
        if ($raw === '') {
            return;
        }

        // Treat bearer-auth requests as API requests so CSRF failures (if any)
        // return JSON instead of HTML/redirect.
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        }
        if (empty($_SERVER['HTTP_ACCEPT'])) {
            $_SERVER['HTTP_ACCEPT'] = 'application/json';
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT t.id AS token_id, u.id, u.username, u.name, u.email, u.role
                 FROM api_tokens t
                 JOIN users u ON u.id = t.user_id
                 WHERE t.token_hash = ?
                   AND t.revoked_at IS NULL
                   AND t.expires_at > NOW()
                   AND u.active = 1
                 LIMIT 1'
            );
            $stmt->execute([hash('sha256', $raw)]);
            $row = $stmt->fetch();
            if (!$row) {
                return;
            }

            self::$tokenId = (int)$row['token_id'];
            self::$tokenUser = [
                'id'       => (int)$row['id'],
                'username' => (string)$row['username'],
                'name'     => (string)$row['name'],
                'email'    => (string)$row['email'],
                'role'     => (string)$row['role'],
            ];

            // Κυλιόμενη λήξη: όσο χρησιμοποιείται το app, ο χρήστης δεν ξανασυνδέεται.
            $db->prepare(
                'UPDATE api_tokens
                 SET last_used_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ' . (int)API_TOKEN_TTL_DAYS . ' DAY)
                 WHERE id = ?'
            )->execute([self::$tokenId]);
        } catch (Throwable $e) {
            // Ο πίνακας μπορεί να μην υπάρχει ακόμη (πριν το migration).
            self::$tokenUser = null;
            self::$tokenId = 0;
        }
    }

    /** Επιστρέφει το raw token από το Authorization header, ή κενό. */
    private static function bearerToken(): string {
        $header = '';
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (!empty($_SERVER[$key])) {
                $header = (string)$_SERVER[$key];
                break;
            }
        }

        // Ο Apache χωρίς CGIPassAuth κόβει το header από το $_SERVER.
        if ($header === '' && function_exists('apache_request_headers')) {
            foreach ((array)apache_request_headers() as $name => $value) {
                if (strcasecmp((string)$name, 'Authorization') === 0) {
                    $header = (string)$value;
                    break;
                }
            }
        }

        if (!preg_match('/^Bearer\s+([A-Za-z0-9._\-]+)$/i', trim($header), $m)) {
            return '';
        }
        return $m[1];
    }

    private static function jsonError(string $message, int $status): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
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
