<?php
/**
 * CLI-only package QA. Uses local config privately, never Database::getInstance().
 * All SQL writes target a newly created random database, dropped in finally.
 * No application bootstrap, SMTP transport, photo files, or attendance schema.
 * Run with configured PHP 8.3 + pdo_mysql + mbstring from this source workspace.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
error_reporting(E_ALL);

class BulkResponse extends RuntimeException {
    public function __construct(public array $body, public int $status) { parent::__construct('QA response'); }
}
class BulkDenied extends RuntimeException {}
class Auth {
    public static string $role = 'admin';
    public static array $events = [];
    public static function requireRole(string $role): void {
        self::$events[] = 'role';
        if ($role !== 'admin' || self::$role !== 'admin') throw new BulkDenied('role');
    }
}
class Controller {
    // Deliberately uninitialized for guard/validation tests: no DB connection exists.
    protected PDO $db;
    public function __construct(?PDO $db = null) { if ($db !== null) $this->db = $db; }
    protected function verifyCsrf(): void {
        Auth::$events[] = 'csrf';
        if (!is_string($_POST['_token'] ?? null) || !hash_equals('qa-csrf', $_POST['_token'])) {
            throw new BulkDenied('csrf');
        }
    }
    protected function json(mixed $body, int $status = 200): void { throw new BulkResponse($body, $status); }
}
// Even an accidental future call cannot create a real SMTP transport.
class Mailer {
    public function __construct() { throw new RuntimeException('Mail forbidden in QA'); }
    public static function __callStatic(string $name, array $arguments): never {
        throw new RuntimeException('Mail forbidden in QA');
    }
}

$root = dirname(__DIR__);
$controllerPath = $root . '/controllers/ChildrenController.php';
require $controllerPath;
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function request(?PDO $db, array $post, string $role = 'admin', bool $addToken = true): BulkResponse {
    Auth::$role = $role;
    Auth::$events = [];
    $_POST = $addToken ? $post + ['_token' => 'qa-csrf'] : $post;
    try {
        (new ChildrenController($db))->apiBulkAction();
    } catch (BulkResponse $response) {
        return $response;
    }
    throw new RuntimeException('FAIL: response missing');
}
function success(BulkResponse $response, int $affected): bool {
    return $response->status === 200 && $response->body === ['success' => true, 'affected' => $affected];
}
function genericFailure(BulkResponse $response): bool {
    return $response->status === 500 && $response->body === ['error' => 'Αποτυχία μαζικής ενέργειας.'];
}
/** Extract complete method bytes using PHP tokens, without normalizing CRLF. */
function methodBytes(string $source): array {
    $tokens = token_get_all($source);
    $result = [];
    for ($i = 0; $i < count($tokens); $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $text = $tokens[$i][1];
        $name = null;
        $depth = 0;
        $opened = false;
        while (++$i < count($tokens)) {
            $token = $tokens[$i];
            $text .= is_array($token) ? $token[1] : $token;
            if ($name === null && is_array($token) && $token[0] === T_STRING) $name = $token[1];
            if ($token === '{') { $opened = true; $depth++; }
            if ($token === '}' && --$depth === 0 && $opened) break;
        }
        $result[$name] = $text;
    }
    return $result;
}
function localConnection(string $name = ''): PDO {
    if ($name !== '' && !preg_match('/^kinderlink_children_bulk_test_[a-f0-9]{24}$/D', $name)) {
        throw new RuntimeException('FAIL: unsafe database name');
    }
    // Never use DB_NAME, a configured DSN, or a configurable remote host/port.
    return new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4' . ($name === '' ? '' : ';dbname=' . $name), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

$server = null;
$db = null;
$created = false;
$name = '';
$exit = 0;
try {
    $packaged = methodBytes(file_get_contents($controllerPath));
    foreach (['apiBulkAction', 'cloneLastName'] as $method) {
        check(isset($packaged[$method]), 'controller method present: ' . $method);
    }
    check(str_contains($packaged['apiBulkAction'], 'beginTransaction()'), 'bulk clone transaction present');
    check(str_contains($packaged['apiBulkAction'], 'rollBack()'), 'bulk clone rollback present');

    $actions = ['activate', 'deactivate', 'clone', 'delete'];
    foreach ($actions as $action) {
        foreach (['teacher', 'parent', 'anonymous'] as $role) {
            $denied = false;
            try { request(null, ['action' => $action, 'ids' => [1]], $role); }
            catch (BulkDenied $e) { $denied = $e->getMessage() === 'role'; }
            check($denied && Auth::$events === ['role'], "$action: $role denied before CSRF/DB");
        }
        foreach (['missing' => null, 'wrong' => 'bad', 'array' => [], 'empty' => ''] as $label => $token) {
            $post = ['action' => $action, 'ids' => [1]];
            if ($token !== null) $post['_token'] = $token;
            $denied = false;
            try { request(null, $post, 'admin', false); }
            catch (BulkDenied $e) { $denied = $e->getMessage() === 'csrf'; }
            check($denied && Auth::$events === ['role', 'csrf'], "$action: $label CSRF denied before DB");
        }
    }
    foreach ([null, '', 'purge', 'ACTIVATE', ' clone ', [], 1, true] as $i => $action) {
        $r = request(null, ['action' => $action, 'ids' => [1]]);
        check($r->status === 422 && Auth::$events === ['role', 'csrf'], "invalid action $i rejected before DB");
    }
    $invalidIds = [
        'missing' => null, 'empty' => [], 'scalar' => '1', 'integer scalar' => 1,
        'associative' => ['id' => 1], 'sparse' => [1 => 1], 'nested' => [[1]],
        'zero' => [0], 'negative' => [-1], 'text' => ['oops'], 'numeric suffix' => ['1x'],
        'float' => [1.0], 'decimal' => ['1.5'], 'exponent' => ['1e1'], 'boolean' => [true],
        'null element' => [null], 'whitespace' => [' 1'], 'trailing newline' => ["1\n"],
        'leading zero' => ['01'], 'plus' => ['+1'], 'overflow' => [str_repeat('9', 50)],
        'mixed invalid' => [1, 'bad'], 'injection' => ['1) OR 1=1'],
        'over limit' => range(1, 101), 'duplicate over limit' => array_fill(0, 101, 1),
    ];
    foreach ($actions as $action) {
        foreach ($invalidIds as $label => $ids) {
            $post = ['action' => $action];
            if ($ids !== null) $post['ids'] = $ids;
            $r = request(null, $post);
            check($r->status === 422 && Auth::$events === ['role', 'csrf'], "$action: $label IDs rejected before DB");
        }
    }

    require $root . '/config/config.php';
    if (!in_array(strtolower((string)DB_HOST), ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('FAIL: non-local configuration refused');
    }
    check(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 3 && extension_loaded('pdo_mysql') && extension_loaded('mbstring'), 'PHP 8.3, PDO MySQL and mbstring available');
    $name = 'kinderlink_children_bulk_test_' . bin2hex(random_bytes(12));
    $server = localConnection();
    check($server->query('SELECT DATABASE()')->fetchColumn() === null, 'server connection has no selected school database');
    // No IF NOT EXISTS: a collision must fail, never reuse any existing database.
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db = localConnection($name);
    check($db->query('SELECT DATABASE()')->fetchColumn() === $name && $name !== (string)DB_NAME, 'all fixture SQL bound to freshly created random database');
    $db->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
    foreach ([
        'CREATE TABLE users (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB',
        'CREATE TABLE children (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, dob DATE NULL, mother_mobile VARCHAR(20) NULL, father_mobile VARCHAR(20) NULL, email1 VARCHAR(255) NULL, email2 VARCHAR(255) NULL, send_email1 TINYINT NOT NULL DEFAULT 1, send_email2 TINYINT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, parent_user_id INT UNSIGNED NULL, FOREIGN KEY (parent_user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB',
        'CREATE TABLE parameters (param_key VARCHAR(100) PRIMARY KEY, param_value VARCHAR(255)) ENGINE=InnoDB',
        "INSERT INTO parameters VALUES ('email_mode','virtual')",
        'INSERT INTO users VALUES (1)',
    ] as $sql) $db->exec($sql);
    // Synthetic related rows reproduce existing FKs only; no photo files are created.
    foreach (['children_groups', 'messages', 'message_photos', 'income'] as $table) {
        $db->exec("CREATE TABLE `$table` (id INT UNSIGNED PRIMARY KEY, child_id INT UNSIGNED NOT NULL, FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE) ENGINE=InnoDB");
    }
    check($db->query("SELECT param_value FROM parameters WHERE param_key='email_mode'")->fetchColumn() === 'virtual' && !class_exists('Database', false), 'virtual-only fixture; real application database bootstrap not loaded');
    $insert = $db->prepare('INSERT INTO children (id,first_name,last_name,dob,mother_mobile,father_mobile,email1,email2,send_email1,send_email2,active,parent_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $insert->execute([1, 'Αθηνά', '  Δοκιμή  ', '2020-01-02', '111', '222', 'a@example.invalid', 'b@example.invalid', 0, 1, 1, 1]);
    $insert->execute([2, 'Null', 'Null', null, null, null, null, null, 1, 0, 0, null]);
    $insert->execute([3, 'Unicode', str_repeat('Ω😀', 50), null, '', '', '', '', 0, 0, 1, null]);
    foreach (['children_groups', 'messages', 'message_photos', 'income'] as $table) $db->exec("INSERT INTO `$table` VALUES (1,1)");
    foreach ($actions as $action) {
        check(success(request($db, ['action' => $action, 'ids' => [999999]]), 0) && !$db->inTransaction(), "$action: no existing selected rows succeeds with zero");
    }
    check(success(request($db, ['action' => 'deactivate', 'ids' => ['1', 3]]), 2), 'deactivate selected children');
    check($db->query('SELECT active FROM children ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) === [0, 0, 0], 'deactivate state persisted');
    check(success(request($db, ['action' => 'activate', 'ids' => [1, '1', 2]]), 2), 'activate deduplicates integer/string IDs');
    check($db->query('SELECT active FROM children ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) === [1, 1, 0], 'activate leaves unselected row unchanged');
    check(success(request($db, ['action' => 'activate', 'ids' => [1, 2]]), 0), 'already active reports zero changed rows');

    $sources = $db->query('SELECT * FROM children ORDER BY id')->fetchAll();
    check(success(request($db, ['action' => 'clone', 'ids' => [1, '1', 2, 3, 999999]]), 3) && !$db->inTransaction(), 'clone deduplicates, ignores nonexistent IDs and commits all copies');
    $copies = $db->query('SELECT * FROM children WHERE id>3')->fetchAll();
    foreach ($sources as $source) {
        $copy = array_values(array_filter($copies, static fn(array $row): bool => $row['first_name'] === $source['first_name']))[0];
        $expected = rtrim(mb_substr(trim($source['last_name']), 0, 93, 'UTF-8')) . ' (Copy)';
        check($copy['last_name'] === $expected && mb_strlen($copy['last_name'], 'UTF-8') <= 100 && mb_check_encoding($copy['last_name'], 'UTF-8'), 'clone suffix/truncation/UTF-8: ' . $source['first_name']);
        unset($copy['id'], $copy['last_name'], $source['id'], $source['last_name']);
        check($copy === $source, 'clone preserves every child field, parent/null: ' . $source['first_name']);
    }
    foreach (['children_groups', 'messages', 'message_photos', 'income'] as $table) {
        check((int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() === 1, 'clone does not copy related rows: ' . $table);
    }
    $nullCopy = (int)$db->query("SELECT id FROM children WHERE first_name='Null' AND id>3")->fetchColumn();
    check(success(request($db, ['action' => 'clone', 'ids' => [$nullCopy]]), 1) && (int)$db->query("SELECT COUNT(*) FROM children WHERE last_name='Null (Copy) (Copy)'")->fetchColumn() === 1, 'cloning a copy appends suffix again (root semantics)');

    // Real insert failure on the SECOND copy proves earlier inserts roll back.
    $insert->execute([1001, 'Rollback', 'Afirst', null, null, null, null, null, 1, 0, 1, null]);
    $insert->execute([1002, 'Rollback', 'Zfail', null, null, null, null, null, 1, 0, 1, null]);
    $before = $db->query('SELECT * FROM children ORDER BY id')->fetchAll();
    $db->exec('SET @qa_clone_attempts = 0');
    $db->exec("CREATE TRIGGER qa_fail_copy BEFORE INSERT ON children FOR EACH ROW BEGIN SET @qa_clone_attempts = @qa_clone_attempts + 1; IF NEW.last_name = 'Zfail (Copy)' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'qa-private-marker'; END IF; END");
    $r = request($db, ['action' => 'clone', 'ids' => [1001, 1002]]);
    check(genericFailure($r), 'insert failure exposes only generic error, not SQL or trigger detail');
    check((int)$db->query('SELECT @qa_clone_attempts')->fetchColumn() === 2, 'failure occurred after first successful clone insert');
    check(!$db->inTransaction() && $db->query('SELECT * FROM children ORDER BY id')->fetchAll() === $before, 'failed clone rolled back all copies; originals unchanged');
    $db->exec('DROP TRIGGER qa_fail_copy');
    check(success(request($db, ['action' => 'clone', 'ids' => [1001, 1002]]), 2), 'clone succeeds after failed transaction cleanup');

    // All four operations handle DB failures without leaking details.
    $db->exec('RENAME TABLE children TO qa_children_saved');
    try {
        foreach ($actions as $action) {
            check(genericFailure(request($db, ['action' => $action, 'ids' => [1]])) && !$db->inTransaction(), "$action: database failure is generic and leaves no transaction");
        }
    } finally { $db->exec('RENAME TABLE qa_children_saved TO children'); }

    $db->exec('CREATE TABLE qa_restrict (child_id INT UNSIGNED, FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE RESTRICT) ENGINE=InnoDB');
    $db->exec('INSERT INTO qa_restrict VALUES (2)');
    check(genericFailure(request($db, ['action' => 'delete', 'ids' => [1, 2]])) && (int)$db->query('SELECT COUNT(*) FROM children WHERE id IN (1,2)')->fetchColumn() === 2, 'delete respects restrictive FK; failed statement does not partially delete');
    $db->exec('DROP TABLE qa_restrict');
    check(success(request($db, ['action' => 'delete', 'ids' => [1, '1', 999999]]), 1), 'delete deduplicates and counts existing children only');
    foreach (['children_groups', 'messages', 'message_photos', 'income'] as $table) {
        check((int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() === 0, 'existing FK cascades delete related metadata: ' . $table);
    }
    check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'child delete preserves linked parent user');
    check((int)$db->query('SELECT COUNT(*) FROM children WHERE id=2')->fetchColumn() === 1, 'child delete preserves unselected children');

    foreach (range(2001, 2100) as $id) $insert->execute([$id, 'Page', 'Child ' . $id, null, null, null, null, null, 1, 0, 0, null]);
    foreach (['activate', 'deactivate', 'clone', 'delete'] as $action) {
        check(success(request($db, ['action' => $action, 'ids' => range(2001, 2100)]), 100), "$action: full 100-child page accepted");
    }
    check(!$db->inTransaction(), 'no open transaction after full-page operations');
    echo "All $checks checks passed; isolated local MySQL only; no real mail/photo operations.\n";
} catch (Throwable $e) {
    // Unexpected exceptions can contain paths, connection settings or SQL values.
    fwrite(STDERR, $e instanceof RuntimeException && str_starts_with($e->getMessage(), 'FAIL: ')
        ? $e->getMessage() . PHP_EOL : "QA failed (private exception details suppressed).\n");
    $exit = 1;
} finally {
    if ($created && $server instanceof PDO && preg_match('/^kinderlink_children_bulk_test_[a-f0-9]{24}$/D', $name)) {
        try {
            if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
            $db = null;
            $server->exec("DROP DATABASE `$name`");
            echo "Isolated test database removed.\n";
        } catch (Throwable $e) {
            fwrite(STDERR, "QA cleanup failed; remove isolated database: $name\n");
            $exit = 1;
        }
    }
}
exit($exit);