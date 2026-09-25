<?php
class Controller {
    protected PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Render a view file within a layout
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void {
        extract($data, EXTR_SKIP);
        $user = Auth::user();

        // Capture view content
        ob_start();
        require ROOT . '/views/' . $view . '.php';
        $content = ob_get_clean();

        // Render layout with content
        require ROOT . '/views/layouts/' . $layout . '.php';
    }

    /**
     * Redirect to a URL
     */
    protected function redirect(string $url): void {
        // Prepend BASE_URL for root-relative paths
        if (str_starts_with($url, '/')) {
            $url = BASE_URL . $url;
        }
        header('Location: ' . $url);
        exit;
    }

    /**
     * Return JSON response
     */
    protected function json(mixed $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Get JSON body from request
     */
    protected function getJsonBody(): array {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }

    /**
     * Get posted data (POST array), with optional key
     */
    protected function post(?string $key = null, mixed $default = null): mixed {
        if ($key === null) return $_POST;
        return $_POST[$key] ?? $default;
    }

    /**
     * Sanitize a string value (trim only — do not strip HTML for rich text)
     */
    protected function sanitize(string $value): string {
        return trim($value);
    }

    /**
     * Escape HTML for output
     */
    protected function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * CSRF token generation / validation
     */
    protected function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrf(): void {
        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            // For AJAX requests return JSON, for web form submissions redirect back with message.
            $isAjax = !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
                || (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json')
                || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
            if ($isAjax) {
                http_response_code(403);
                $this->json(['error' => 'CSRF token mismatch'], 403);
            } else {
                // Regenerate token and redirect back so the user gets a fresh form.
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $ref = $_SERVER['HTTP_REFERER'] ?? '';
                $back = ($ref !== '' && str_starts_with($ref, APP_URL)) ? $ref : BASE_URL . '/';
                header('Location: ' . $back . (str_contains($back, '?') ? '&' : '?') . 'csrf_error=1');
                exit;
            }
        }
    }
}
