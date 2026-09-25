<?php
/** Real controller regression tests in a random disposable MySQL database; no app config or mail. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
date_default_timezone_set('UTC');
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../controllers/PersonalController.php';
require_once __DIR__ . '/../controllers/AuthController.php';

final class PersonalTestRedirect extends RuntimeException {}
trait PersonalTestCapture {
    public ?array $rendered = null;
    public ?string $redirected = null;
    public function __construct(PDO $db) { $this->db = $db; }
    protected function render(string $view, array $data = [], string $layout = 'main'): void {
        $this->rendered = $data;
    }
    protected function redirect(string $url): void {
        $this->redirected = $url;
        throw new PersonalTestRedirect();
    }
}
final class PersonalTestController extends PersonalController { use PersonalTestCapture; }
final class PersonalTestLogin extends AuthController { use PersonalTestCapture; }
function personalCheck(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . PHP_EOL;
}

$server = null;
$db = null;
$created = false;
$database = 'kinderlink_personal_test_' . bin2hex(random_bytes(12));
$exit = 0;
$cleanup = static function () use (&$server, &$db, &$created, $database): bool {
    if (!$created) return true;
    try {
        $db = null;
        $server->exec("DROP DATABASE `$database`");
        $created = false;
        echo "Disposable personal-settings database removed.\n";
        return true;
    } catch (Throwable $ignored) {
        fwrite(STDERR, "Cleanup failed; remove only $database manually.\n");
        return false;
    }
};
register_shutdown_function(static function () use ($cleanup): void { $cleanup(); });
ob_start(); // Preserve real session regeneration while collecting assertion output.
try {
    if ($argc !== 1) throw new RuntimeException('No arguments accepted.');
    $host = getenv('KINDERLINK_TEST_DB_HOST') ?: '127.0.0.1';
    $port = getenv('KINDERLINK_TEST_DB_PORT') ?: '3306';
    $user = getenv('KINDERLINK_TEST_DB_USER');
    $password = getenv('KINDERLINK_TEST_DB_PASS');
    if (!in_array($host, ['127.0.0.1', '::1'], true) || !ctype_digit($port)
        || (int)$port < 1 || (int)$port > 65535 || !$user || $password === false) {
        throw new RuntimeException('Explicit local KINDERLINK_TEST_DB_USER/PASS and valid loopback HOST/PORT required.');
    }
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $server = new PDO($dsn, $user, $password, $options);
    $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db = new PDO($dsn . ';dbname=' . $database, $user, $password, $options);
    $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    session_set_save_handler(new class implements SessionHandlerInterface {
        public function open(string $path, string $name): bool { return true; }
        public function close(): bool { return true; }
        public function read(string $id): string|false { return ''; }
        public function write(string $id, string $data): bool { return true; }
        public function destroy(string $id): bool { return true; }
        public function gc(int $max_lifetime): int|false { return 0; }
    }, true);
    session_name('kinderlink_personal_test');
    if (!session_start(['use_cookies' => 0, 'cache_limiter' => ''])) throw new RuntimeException('Session failed.');
    $_SERVER = ['REMOTE_ADDR' => '127.0.0.1', 'REQUEST_METHOD' => 'POST'];
    $cases = [
        'combined admin' => ['mode' => 'combined', 'role' => 'admin', 'change' => true],
        'combined parent' => ['mode' => 'combined', 'role' => 'parent', 'change' => true],
        'default combined' => ['mode' => null, 'change' => true],
        'password only' => ['mode' => 'password', 'change' => true],
        'combined blank password' => ['mode' => 'combined', 'blank' => true],
        'profile only ignores password' => ['mode' => 'profile'],
        'wrong current password' => ['mode' => 'combined', 'invalid' => 'current'],
        'mismatched confirmation' => ['mode' => 'combined', 'invalid' => 'confirmation'],
        'weak password' => ['mode' => 'combined', 'invalid' => 'strength'],
        'empty password-only request' => ['mode' => 'password', 'blank' => true, 'invalid' => 'empty'],
        'invalid mode' => ['mode' => 'unknown', 'invalid' => 'mode'],
    ];
    foreach ($cases as $label => $case) {
        $old = 'Old!a9' . bin2hex(random_bytes(12));
        $new = 'New!a9' . bin2hex(random_bytes(12));
        $username = 'test_' . bin2hex(random_bytes(6));
        $role = $case['role'] ?? 'admin';
        $db->prepare('INSERT INTO users (username,password,name,email,role,active) VALUES (?,?,?,?,?,1)')
            ->execute([$username, Auth::hashPassword($old), 'Before', 'before@example.invalid', $role]);
        $id = (int)$db->lastInsertId();
        $row = $db->prepare('SELECT * FROM users WHERE id=?');
        $row->execute([$id]);
        $before = $row->fetch();
        $_SESSION = ['csrf_token' => bin2hex(random_bytes(32))];
        Auth::login($before);
        $newInput = !empty($case['blank']) ? '' : ((($case['invalid'] ?? '') === 'strength') ? 'weak' : $new);
        $_POST = ['_token' => $_SESSION['csrf_token'], 'name' => 'After', 'username' => $username,
            'email' => 'after@example.invalid', 'current_password' => ($case['invalid'] ?? '') === 'current' ? 'wrong' : $old,
            'new_password' => $newInput,
            'confirm_password' => ($case['invalid'] ?? '') === 'confirmation' ? $new . 'x' : $newInput];
        if ($case['mode'] !== null) $_POST['form_mode'] = $case['mode'];
        $controller = new PersonalTestController($db);
        try { $controller->save(); } catch (PersonalTestRedirect $expected) {}
        $row->execute([$id]);
        $after = $row->fetch();
        if (isset($case['invalid'])) {
            personalCheck($before === $after && !empty($controller->rendered['errors'])
                && $controller->redirected === null, $label . ': rejected without writes');
            continue;
        }
        $changed = $case['change'] ?? false;
        personalCheck(Auth::verifyPassword($changed ? $new : $old, $after['password'])
            && ($changed ? !Auth::verifyPassword($old, $after['password']) : $before['password'] === $after['password']),
            $label . ': correct stored password');
        $expectedName = $case['mode'] === 'password' ? 'Before' : 'After';
        personalCheck($after['name'] === $expectedName && Auth::user()['name'] === $expectedName
            && $controller->redirected === ($role === 'parent' ? '/parent' : '/administration') . '/personal-details?saved=1',
            $label . ': profile, session and redirect correct');
        if ($changed) {
            foreach ([$old => false, $new => true] as $plain => $accept) {
                $_SESSION = ['csrf_token' => bin2hex(random_bytes(32))];
                $_POST = ['_token' => $_SESSION['csrf_token'], 'username' => $username, 'password' => $plain];
                $login = new PersonalTestLogin($db);
                try { $login->login(); } catch (PersonalTestRedirect $expected) {}
                personalCheck(Auth::check() === $accept, $label . ($accept ? ': new login accepted' : ': old login rejected'));
            }
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) === RuntimeException::class ? $e->getMessage() . PHP_EOL : "Test failed; private diagnostics suppressed.\n");
    $exit = 1;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; session_destroy(); }
    if (!$cleanup()) $exit = 1;
    ob_end_flush();
}
exit($exit);