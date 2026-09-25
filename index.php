<?php
/**
 * Front Controller / Entry Point
 */

// Force output buffering on regardless of server php.ini setting.
// This ensures header() calls in controllers (e.g. binary file downloads)
// are never blocked by early/stray output on shared hosting servers.
if (ob_get_level() === 0) {
    ob_start();
}

define('ROOT', __DIR__);

// Security headers (kept compatible with existing inline scripts/styles).
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; connect-src 'self'; img-src 'self' data: https:; font-src 'self' data: https:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net");

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

require_once ROOT . '/config/config.php';
require_once ROOT . '/config/database.php';
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Router.php';
require_once ROOT . '/core/Controller.php';
require_once ROOT . '/core/Mailer.php';

// Optional Composer autoloader for operator-added dependencies; core uses native SMTP.
$composerAutoload = ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Auto-load controllers and models
spl_autoload_register(function (string $class): void {
    $paths = [ROOT . '/controllers/', ROOT . '/models/'];
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Start session
session_name(SESSION_NAME);
function app_is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    return strtolower($forwardedProto) === 'https';
}

session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => BASE_URL . '/',
    'secure'   => app_is_https_request(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();

// Dispatch
$router = new Router();
$router->dispatch();
