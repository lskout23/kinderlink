<?php
/** Disposable localhost login regression test; never loads application config or env files. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
date_default_timezone_set('UTC');
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../controllers/AuthController.php';

function expiryCheck(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . PHP_EOL;
}

function expiryConnection(array $settings, string $database = ''): PDO {
    if (!in_array($settings['host'] ?? '', ['127.0.0.1', '::1'], true)
        || !ctype_digit((string)($settings['port'] ?? ''))
        || (int)$settings['port'] < 1 || (int)$settings['port'] > 65535
        || ($database !== '' && !preg_match('/\Akinderlink_auth_test_[a-f0-9]{24}\z/', $database))) {
        throw new RuntimeException('Refused non-local host, invalid port or non-test database.');
    }
    $db = new PDO('mysql:host=' . $settings['host'] . ';port=' . $settings['port'] . ';charset=utf8mb4'
        . ($database === '' ? '' : ';dbname=' . $database), $settings['user'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true, // Only the fixed local schema is imported.
        ]);
    $db->exec("SET time_zone = '+00:00'");
    return $db;
}

final class ExpiryTestRedirect extends RuntimeException {}

final class ExpiryTestController extends AuthController {
    public ?array $rendered = null;
    public ?string $redirected = null;

    public function __construct(PDO $db) {
        // Do not call Controller::__construct(): it would load the application database singleton.
        $this->db = $db;
    }

    protected function render(string $view, array $data = [], string $layout = 'main'): void {
        $this->rendered = ['view' => $view, 'error' => $data['error'] ?? null, 'layout' => $layout];
    }

    protected function redirect(string $url): void {
        $this->redirected = $url;
        // Preserve redirect's non-returning behavior without terminating observation of the session.
        throw new ExpiryTestRedirect();
    }
}

function expiryWorker(): never {
    ob_start(); // Auth::login() must be able to regenerate the real PHP session before output.
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    $exit = 0;
    $result = null;
    try {
        $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($input) || empty($input['database'])) throw new RuntimeException('Invalid worker input.');
        $db = expiryConnection($input['settings'], $input['database']);

        // Real session_start/regenerate_id, but no shared session directory or persistent auth files.
        session_set_save_handler(new class implements SessionHandlerInterface {
            public function open(string $path, string $name): bool { return true; }
            public function close(): bool { return true; }
            public function read(string $id): string|false { return ''; }
            public function write(string $id, string $data): bool { return true; }
            public function destroy(string $id): bool { return true; }
            public function gc(int $max_lifetime): int|false { return 0; }
        }, true);
        session_name('kinderlink_auth_test');
        if (!session_start(['use_cookies' => 0, 'cache_limiter' => ''])) {
            throw new RuntimeException('Cannot start isolated session.');
        }
        $_SESSION = ['csrf_token' => bin2hex(random_bytes(32))];
        $sessionBefore = session_id();
        $_SERVER = ['REMOTE_ADDR' => '127.0.0.1', 'REQUEST_METHOD' => 'POST'];
        $_POST = ['username' => $input['username'], 'password' => $input['password'],
            '_token' => $_SESSION['csrf_token']];
        $controller = new ExpiryTestController($db);
        try {
            $controller->login(); // Actual SELECT, password/expiry checks, rate limits and Auth::login().
        } catch (ExpiryTestRedirect $expected) {}
        $authKeys = array_intersect_key($_SESSION, array_flip([
            'user_id', 'username', 'name', 'email', 'role', 'logged_in',
        ]));
        $result = [
            'authenticated' => Auth::check(),
            'auth_keys' => array_keys($authKeys),
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'session_regenerated' => session_id() !== $sessionBefore,
            'rendered' => $controller->rendered,
            'redirected' => $controller->redirected,
        ];
    } catch (Throwable $ignored) {
        $exit = 1;
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        restore_error_handler();
        ob_end_clean();
    }
    if ($exit === 0) echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
    else fwrite(STDERR, "Login worker failed; private diagnostics suppressed.\n");
    exit($exit);
}

if ($argc === 2 && $argv[1] === '--worker') expiryWorker();

function expiryLogin(array $environment, array $input): array {
    // No shell, credentials in argv, inherited app environment, auto-prepend config, or mail transport.
    $process = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=',
        '-d', 'session.auto_start=0', '-d', 'display_errors=0', '-d', 'log_errors=0',
        '-d', 'disable_functions=mail,fsockopen,pfsockopen,stream_socket_client,curl_exec,curl_multi_exec',
        __FILE__, '--worker'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes, __DIR__, $environment);
    if (!is_resource($process)) throw new RuntimeException('Cannot start login subprocess.');
    try {
        $payload = json_encode($input, JSON_THROW_ON_ERROR);
        $offset = 0;
        while ($offset < strlen($payload)) {
            $written = fwrite($pipes[0], substr($payload, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('Cannot write private worker input.');
            $offset += $written;
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        $status = proc_close($process); // Reap before the caller can drop the disposable database.
    }
    if ($status !== 0 || $errors !== '') throw new RuntimeException('Login subprocess failed; private diagnostics suppressed.');
    $result = json_decode($output, true);
    if (!is_array($result)) throw new RuntimeException('Invalid login subprocess response.');
    return $result;
}

$server = null;
$db = null;
$created = false;
$name = '';
$settings = [];
$exit = 0;
$cleanup = static function () use (&$server, &$db, &$created, &$name, &$settings): bool {
    if (!$created) return true;
    $db = null;
    for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
            if ($attempt > 0 || !$server) $server = expiryConnection($settings);
            $server->exec("DROP DATABASE IF EXISTS `$name`");
            $created = false;
            echo "Disposable auth-expiry database removed.\n";
            return true;
        } catch (Throwable $ignored) { $server = null; }
    }
    fwrite(STDERR, "Cleanup failed; remove only the disposable database $name manually.\n");
    return false;
};
register_shutdown_function(static function () use ($cleanup): void { $cleanup(); });

try {
    if ($argc !== 1) throw new RuntimeException('No public arguments accepted; database names and SQL paths cannot be supplied.');
    $host = getenv('KINDERLINK_TEST_DB_HOST');
    $host = $host === false ? '127.0.0.1' : strtolower($host);
    if ($host === 'localhost') $host = '127.0.0.1';
    $port = getenv('KINDERLINK_TEST_DB_PORT');
    $user = getenv('KINDERLINK_TEST_DB_USER');
    $password = getenv('KINDERLINK_TEST_DB_PASS');
    if ($user === false || $user === '' || $password === false) {
        throw new RuntimeException('Set explicit KINDERLINK_TEST_DB_USER and KINDERLINK_TEST_DB_PASS.');
    }
    $settings = ['host' => $host, 'port' => $port === false ? '3306' : $port,
        'user' => $user, 'password' => $password];
    $name = 'kinderlink_auth_test_' . bin2hex(random_bytes(12));
    $server = expiryConnection($settings);
    // Never reuse or drop an existing database on a name collision.
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db = expiryConnection($settings, $name);
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($schema === false) throw new RuntimeException('Cannot read the local KinderLink schema.');
    $db->exec($schema);

    $environment = [];
    foreach (['SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP', 'TMPDIR'] as $key) {
        $value = getenv($key);
        if ($value !== false) $environment[$key] = $value;
    }
    $plain = 'Test!a9' . bin2hex(random_bytes(16));
    $hash = Auth::hashPassword($plain);
    $cases = [
        'expired' => ['expiry' => gmdate('Y-m-d H:i:s', time() - 86400), 'accept' => false],
        'future' => ['expiry' => gmdate('Y-m-d H:i:s', time() + 86400), 'accept' => true],
        'null' => ['expiry' => null, 'accept' => true],
        'invalid' => ['expiry' => gmdate('Y-m-d H:i:s', time() + 86400), 'accept' => false],
    ];
    foreach ($cases as $case => $fixture) {
        $username = 'expiry_test_' . $case;
        $db->prepare('INSERT INTO users (username, password, name, email, role, active, temp_password_expires)
            VALUES (?, ?, ?, ?, ?, 1, ?)')->execute([
                $username, $hash, 'Synthetic login test', $username . '@example.invalid', 'admin', $fixture['expiry'],
            ]);
        $id = (int)$db->lastInsertId();
        $result = expiryLogin($environment, ['settings' => $settings, 'database' => $name,
            'username' => $username, 'password' => $case === 'invalid' ? $plain . '-wrong' : $plain]);
        if ($fixture['accept']) {
            expiryCheck(($result['authenticated'] ?? false) === true
                && (int)($result['user_id'] ?? 0) === $id && ($result['username'] ?? '') === $username
                && ($result['role'] ?? '') === 'admin' && ($result['session_regenerated'] ?? false) === true
                && ($result['redirected'] ?? '') === '/dashboard' && $result['rendered'] === null,
                $case . ': real login authenticates and regenerates session');
        } else {
            $expectedError = $case === 'expired'
                ? 'Ο προσωρινός κωδικός έχει λήξει. Παρακαλώ ζητήστε νέο κωδικό ανάκτησης.'
                : 'Λάθος όνομα χρήστη ή κωδικός.';
            expiryCheck(($result['authenticated'] ?? null) === false && ($result['auth_keys'] ?? null) === []
                && $result['redirected'] === null && ($result['session_regenerated'] ?? null) === false
                && ($result['rendered']['view'] ?? '') === 'auth/login'
                && ($result['rendered']['layout'] ?? '') === 'public'
                && ($result['rendered']['error'] ?? '') === $expectedError,
                $case . ': correct refusal with no session authentication');
        }
        $stmt = $db->prepare('SELECT password, temp_password_expires FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        expiryCheck($row && $row['password'] === $hash
            && $row['temp_password_expires'] === ($fixture['accept'] ? null : $fixture['expiry']),
            $case . ': password unchanged; expiry ' . ($fixture['accept'] ? 'cleared or remains NULL' : 'preserved'));
    }
    echo "All auth-expiry checks passed; no SMTP, application config, existing school database or credentials printed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) === RuntimeException::class ? $e->getMessage() . PHP_EOL
        : "Auth-expiry test failed; private diagnostics suppressed.\n");
    $exit = 1;
} finally {
    if (!$cleanup()) $exit = 1;
}
exit($exit);