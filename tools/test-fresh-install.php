<?php
/** Local, disposable integration test. No application config or env file is loaded. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Attendance.php';

function freshCheck(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . PHP_EOL;
}

function freshConnection(array $settings, string $database = ''): PDO {
    if ($database !== '' && !preg_match('/\Akinderlink_fresh_test_[a-f0-9]{24}\z/', $database)) {
        throw new RuntimeException('Refused non-test database.');
    }
    return new PDO('mysql:host=' . $settings['host'] . ';port=' . $settings['port'] . ';charset=utf8mb4'
        . ($database === '' ? '' : ';dbname=' . $database), $settings['user'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true, // Only the fixed local schema file is imported.
        ]);
}

function startAdmin(array $environment, string $username, string $email): array {
    // A command array avoids the shell. No credentials/passwords are command arguments.
    $process = proc_open([PHP_BINARY, __DIR__ . '/create-admin.php', '--confirm-new-install',
        '--username=' . $username, '--email=' . $email],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__, $environment);
    if (!is_resource($process)) throw new RuntimeException('Cannot start admin test subprocess.');
    fclose($pipes[0]);
    return [$process, $pipes[1], $pipes[2]];
}

function finishAdmin(array $child): array {
    [$process, $stdout, $stderr] = $child;
    try {
        $output = stream_get_contents($stdout);
        $errors = stream_get_contents($stderr);
    } finally {
        fclose($stdout);
        fclose($stderr);
        $status = proc_close($process);
    }
    // Never echo either subprocess stream, even when JSON decoding or assertions fail.
    return ['status' => $status, 'output' => $output, 'errors' => $errors];
}

function verifyCreatedAdmin(array $result, PDO $db, string $username, string $email): void {
    freshCheck($result['status'] === 0 && $result['errors'] === '', 'first admin subprocess succeeded');
    $payload = json_decode($result['output'], true);
    freshCheck(is_array($payload) && ($payload['status'] ?? '') === 'created'
        && ($payload['username'] ?? '') === $username && ($payload['email'] ?? '') === $email
        && is_string($payload['initial_password'] ?? null), 'private provisioning response is valid');
    $password = $payload['initial_password'];
    $row = $db->query('SELECT * FROM users')->fetch();
    freshCheck((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1
        && $row['username'] === $username && $row['email'] === $email
        && $row['role'] === 'admin' && (int)$row['active'] === 1
        && $row['temp_password_expires'] === null, 'exactly one active administrator, no temporary reset expiry');
    freshCheck(strlen($password) === 32 && Auth::validatePasswordStrength($password) === ''
        && $row['password'] !== $password && Auth::verifyPassword($password, $row['password'])
        && (password_get_info($row['password'])['algoName'] ?? '') === 'bcrypt',
        'random initial password matches controller-compatible bcrypt hash');
    unset($password, $payload, $result);
}

$server = null;
$db = null;
$created = false;
$name = '';
$settings = [];
$exit = 0;
$children = [];
// Cleanup is idempotent, runs in finally and again at shutdown if a fatal error interrupts it.
$cleanup = static function () use (&$server, &$db, &$created, &$name, &$settings): bool {
    if (!$created) return true;
    $db = null;
    for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
            if ($attempt > 0 || !$server) $server = freshConnection($settings);
            // Name is generated here, never accepted from argv, environment or application config.
            $server->exec("DROP DATABASE IF EXISTS `$name`");
            $created = false;
            echo "Disposable fresh-install database removed.\n";
            return true;
        } catch (Throwable $ignored) {
            $server = null;
        }
    }
    fwrite(STDERR, "Cleanup failed; remove the disposable database $name manually. No school database was targeted.\n");
    return false;
};
register_shutdown_function(static function () use ($cleanup): void { $cleanup(); });

try {
    if ($argc !== 1) throw new RuntimeException('No arguments accepted; database names and SQL paths cannot be supplied.');
    $host = getenv('KINDERLINK_TEST_DB_HOST');
    $host = $host === false ? '127.0.0.1' : strtolower($host);
    if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('Only literal loopback database hosts are permitted.');
    }
    // Never resolve "localhost" through DNS or use an implicit socket to another server.
    if ($host === 'localhost') $host = '127.0.0.1';
    $port = getenv('KINDERLINK_TEST_DB_PORT');
    $port = $port === false ? '3306' : $port;
    $user = getenv('KINDERLINK_TEST_DB_USER');
    $password = getenv('KINDERLINK_TEST_DB_PASS');
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535
        || $user === false || $user === '' || $password === false) {
        throw new RuntimeException('Set explicit KINDERLINK_TEST_DB_USER and KINDERLINK_TEST_DB_PASS; optional DB_PORT must be valid.');
    }
    $settings = ['host' => $host, 'port' => $port, 'user' => $user, 'password' => $password];
    $name = 'kinderlink_fresh_test_' . bin2hex(random_bytes(12));
    $server = freshConnection($settings);
    // No IF NOT EXISTS: an improbable collision must fail without using or dropping an existing DB.
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db = freshConnection($settings, $name);
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($schema === false) throw new RuntimeException('Cannot read the local KinderLink schema.');
    $db->exec($schema);
    freshCheck((int)$db->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn() === 1, 'full schema imported with foreign keys enabled');

    $tables = ['users', 'children', 'child_attendance', 'groups', 'children_groups', 'teacher_groups',
        'activities', 'financial_activities', 'email_template', 'parameters', 'auth_login_attempts',
        'messages', 'message_photos', 'free_emails', 'income', 'expenses', 'parent_threads', 'parent_thread_messages'];
    $actual = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    sort($actual);
    freshCheck($tables === $actual, 'all 18 expected tables and no extra tables');
    foreach ($tables as $table) {
        if (in_array($table, ['activities', 'email_template', 'parameters'], true)) continue;
        freshCheck((int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() === 0, $table . ' starts empty');
    }
    $metadata = $db->query('SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll();
    foreach ($metadata as $table) {
        freshCheck(strtolower($table['ENGINE']) === 'innodb' && $table['TABLE_COLLATION'] === 'utf8mb4_unicode_ci',
            $table['TABLE_NAME'] . ' uses InnoDB and utf8mb4');
    }
    $columns = $db->query('SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
        CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()')->fetchAll();
    $byTable = [];
    foreach ($columns as $column) $byTable[$column['TABLE_NAME']][$column['COLUMN_NAME']] = $column;
    foreach ([
        'users' => ['id', 'username', 'password', 'name', 'email', 'role', 'active', 'temp_password_expires', 'created_at', 'updated_at'],
        'parent_threads' => ['id', 'child_id', 'parent_user_id', 'group_id', 'subject', 'created_at', 'updated_at'],
        'parent_thread_messages' => ['id', 'thread_id', 'sender_id', 'body', 'read_at', 'created_at'],
        'message_photos' => ['id', 'child_id', 'group_id', 'message_date', 'stored_name', 'original_name', 'mime_type', 'size_bytes', 'access_token', 'hidden_at', 'created_by', 'created_at'],
        'auth_login_attempts' => ['key_hash', 'ip_address', 'username_norm', 'fail_count', 'first_fail_at', 'last_fail_at', 'locked_until', 'updated_at'],
        'messages' => ['sleep_minutes', 'email_status'],
    ] as $table => $required) {
        freshCheck(array_diff($required, array_keys($byTable[$table])) === [], $table . ' required columns exist');
    }
    $expiry = $byTable['users']['temp_password_expires'];
    freshCheck($expiry['DATA_TYPE'] === 'datetime' && $expiry['IS_NULLABLE'] === 'YES'
        && ($expiry['COLUMN_DEFAULT'] === null || strtoupper((string)$expiry['COLUMN_DEFAULT']) === 'NULL'),
        'password reset expiry is nullable DATETIME with NULL default');
    freshCheck($byTable['users']['password']['DATA_TYPE'] === 'varchar'
        && (int)$byTable['users']['password']['CHARACTER_MAXIMUM_LENGTH'] === 255
        && $byTable['users']['role']['COLUMN_TYPE'] === "enum('admin','teacher','parent')", 'password and role types match controllers');
    $foreignKeys = $db->query("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('parent_threads', 'parent_thread_messages') AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();
    $links = [];
    foreach ($foreignKeys as $key) $links[$key['TABLE_NAME'] . '.' . $key['COLUMN_NAME']] = $key['REFERENCED_TABLE_NAME'];
    freshCheck(count($links) === 5 && ($links['parent_threads.child_id'] ?? '') === 'children'
        && ($links['parent_threads.parent_user_id'] ?? '') === 'users' && ($links['parent_threads.group_id'] ?? '') === 'groups'
        && ($links['parent_thread_messages.thread_id'] ?? '') === 'parent_threads'
        && ($links['parent_thread_messages.sender_id'] ?? '') === 'users', 'parent messaging foreign keys match migrations');
    freshCheck((new Attendance($db))->isReady(), 'attendance ready immediately without migrations');

    $parameters = $db->query('SELECT param_key, param_value FROM parameters')->fetchAll(PDO::FETCH_KEY_PAIR);
    freshCheck($parameters === array_replace($parameters, ['email_mode' => 'virtual', 'school_name' => 'Το σχολείο μου',
        'school_logo' => '', 'photo_hidden_grace_days' => '15', 'photo_retention_months' => '6', 'photo_retention_days' => '180'])
        && count($parameters) === 6, 'generic school, virtual email and retention defaults');
    freshCheck((int)$db->query('SELECT COUNT(*) FROM activities')->fetchColumn() === 15
        && (int)$db->query("SELECT COUNT(*) FROM activities WHERE type <> 'both'")->fetchColumn() === 0, 'generic activity defaults only');
    $template = $db->query('SELECT template_html, subject FROM email_template')->fetch();
    freshCheck((int)$db->query('SELECT COUNT(*) FROM email_template')->fetchColumn() === 1, 'one generic email template');
    foreach (['|*name*|', '|*breakfast*|', '|*lunch*|', '|*mood*|', '|*sleep*| λεπτά', '|*WC_txt*|', '|*activity*|', '|*comments*|',
        '4 Πολύ Καλά', '3 Καλά', '2 Μέτρια', '1 Καθόλου'] as $token) {
        freshCheck(str_contains($template['template_html'], $token), 'template retains ' . $token);
    }
    freshCheck($template['subject'] === 'Καθημερινή Δραστηριότητα - |*name*|', 'generic template subject');

    // Minimal subprocess environment: no inherited DB_*, SMTP_*, application config or parent env.
    $environment = [];
    foreach (['SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP', 'TMPDIR'] as $key) {
        $value = getenv($key);
        if ($value !== false) $environment[$key] = $value;
    }
    foreach (['DB_HOST' => $host, 'DB_PORT' => $port, 'DB_NAME' => $name, 'DB_USER' => $user, 'DB_PASS' => $password] as $key => $value) {
        $environment['KINDERLINK_INSTALL_' . $key] = $value;
    }
    if ($password === '') $environment['KINDERLINK_INSTALL_ALLOW_EMPTY_LOCAL_PASSWORD'] = '1';
    $result = finishAdmin(startAdmin($environment, 'kinderlink_test_admin', 'admin@example.invalid'));
    verifyCreatedAdmin($result, $db, 'kinderlink_test_admin', 'admin@example.invalid');
    unset($result);
    $snapshot = $db->query('SELECT * FROM users')->fetchAll();
    $second = finishAdmin(startAdmin($environment, 'kinderlink_other_admin', 'other@example.invalid'));
    freshCheck($second['status'] !== 0 && $second['output'] === ''
        && str_contains($second['errors'], 'administrator already exists')
        && $snapshot === $db->query('SELECT * FROM users')->fetchAll(), 'second invocation refuses without output or changes');
    $db->exec('UPDATE users SET active = 0');
    $snapshot = $db->query('SELECT * FROM users')->fetchAll();
    $inactive = finishAdmin(startAdmin($environment, 'kinderlink_other_admin', 'other@example.invalid'));
    freshCheck($inactive['status'] !== 0 && $inactive['output'] === ''
        && str_contains($inactive['errors'], 'administrator already exists')
        && $snapshot === $db->query('SELECT * FROM users')->fetchAll(), 'inactive admin also blocks provisioning');
    $db->exec('UPDATE users SET temp_password_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR)');
    freshCheck((int)$db->query('SELECT COUNT(*) FROM users WHERE temp_password_expires > NOW()')->fetchColumn() === 1,
        'controller password-reset DATE_ADD statement supported');
    $db->exec('UPDATE users SET temp_password_expires = NULL');

    // Only our disposable DB: reset users and race two different usernames against the same lock.
    $db->exec('DELETE FROM users');
    $children[] = startAdmin($environment, 'kinderlink_race_one', 'one@example.invalid');
    $children[] = startAdmin($environment, 'kinderlink_race_two', 'two@example.invalid');
    $results = [];
    while ($children) $results[] = finishAdmin(array_shift($children));
    $successes = array_keys(array_filter($results, static fn(array $r): bool => $r['status'] === 0));
    freshCheck(count($successes) === 1, 'concurrent invocations create only one admin');
    $winner = $successes[0];
    verifyCreatedAdmin($results[$winner], $db, $winner === 0 ? 'kinderlink_race_one' : 'kinderlink_race_two',
        $winner === 0 ? 'one@example.invalid' : 'two@example.invalid');
    $loser = $results[1 - $winner];
    freshCheck($loser['status'] !== 0 && $loser['output'] === ''
        && str_contains($loser['errors'], 'administrator already exists'), 'concurrent loser refuses without revealing credentials');
    unset($results, $loser, $snapshot, $second, $inactive);
    echo "All fresh-install checks passed; no SMTP, no existing school database, no credentials printed.\n";
} catch (Throwable $e) {
    // Exception details from PDO/processes may contain secrets. Only our fixed assertion labels are safe.
    fwrite(STDERR, get_class($e) === RuntimeException::class ? $e->getMessage() . PHP_EOL
        : "Fresh-install test failed; diagnostic details suppressed to protect credentials.\n");
    $exit = 1;
} finally {
    // Reap any started worker before dropping the DB, including partial concurrent-start failures.
    foreach ($children as $child) {
        try { finishAdmin($child); } catch (Throwable $ignored) { $exit = 1; }
    }
    if (!$cleanup()) $exit = 1;
}
exit($exit);