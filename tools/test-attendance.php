<?php
/** Local-only integration tests. Creates/drops its own randomly named database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Match the front controller's output buffering (legacy controller is UTF-8 BOM).
ob_start();
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../core/Controller.php';
require __DIR__ . '/../core/Auth.php';
require __DIR__ . '/../models/Attendance.php';
require __DIR__ . '/../controllers/MessagesController.php';
require __DIR__ . '/../controllers/ParametersController.php';
ob_end_clean();

if (!in_array(strtolower((string)DB_HOST), ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Refusing non-local database.\n"); exit(1);
}
function testDb(string $name = ''): PDO {
    return new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4' . ($name ? ';dbname=' . $name : ''), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
class AttendanceTestMessages extends MessagesController {
    public function __construct(PDO $db) { $this->db = $db; }
}
class AttendanceTestParameters extends ParametersController {
    public function __construct(PDO $db) { $this->db = $db; }
}

if (($argv[1] ?? '') === '--worker') {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    if (!preg_match('/^kinderlink_attendance_test_[a-f0-9]{12}$/D', $input['database'] ?? '')) exit(2);
    $db = testDb($input['database']);
    // Hard safety boundary: tests never permit real email transport.
    if ($db->query("SELECT param_value FROM parameters WHERE param_key='email_mode'")->fetchColumn() !== 'virtual') exit(3);
    $_SESSION = ['logged_in' => true, 'role' => $input['role'], 'user_id' => $input['role'] === 'admin' ? 1 : 2, 'csrf_token' => 'test-csrf'];
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_POST = $input['post'];
    http_response_code(200);
    ob_start();
    register_shutdown_function(static function () {
        $body = ob_get_clean();
        echo json_encode(['status' => http_response_code(), 'body' => json_decode($body, true) ?? $body], JSON_UNESCAPED_UNICODE);
    });
    $actions = ['migrate' => 'apiMigrateAttendance', 'list' => 'apiListByGroup', 'attendance' => 'apiAttendance', 'save' => 'apiSave', 'send' => 'apiSendEmails'];
    if (!isset($actions[$input['action']])) exit(4);
    $controller = $input['action'] === 'migrate' ? new AttendanceTestParameters($db) : new AttendanceTestMessages($db);
    $controller->{$actions[$input['action']]}();
    exit;
}

$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function callApi(string $database, string $action, array $post = [], string $role = 'admin'): array {
    $process = proc_open([PHP_BINARY, __FILE__, '--worker'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Worker start failed.');
    fwrite($pipes[0], json_encode(['database' => $database, 'action' => $action, 'role' => $role, 'post' => $post + ['_token' => 'test-csrf']]));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($process);
    $result = json_decode($output, true);
    if ($status !== 0 || !is_array($result)) {
        $detail = preg_replace('/SQLSTATE[^\r\n]*/', 'SQL error (details suppressed)', $errors . '\nOutput: ' . $output);
        foreach ([DB_PASS, DB_USER] as $secret) {
            if ((string)$secret !== '') $detail = str_replace((string)$secret, '[redacted]', $detail);
        }
        throw new RuntimeException('API test worker failed, exit=' . $status . ': ' . $detail);
    }
    return $result;
}

$name = 'kinderlink_attendance_test_' . bin2hex(random_bytes(6));
$created = false;
$exit = 0;
try {
    $server = testDb();
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db = testDb($name);
    foreach ([
        'CREATE TABLE users (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB',
        'CREATE TABLE children (id INT UNSIGNED PRIMARY KEY, first_name VARCHAR(100), last_name VARCHAR(100), active TINYINT DEFAULT 1, email1 VARCHAR(255), email2 VARCHAR(255), send_email1 TINYINT DEFAULT 1, send_email2 TINYINT DEFAULT 0) ENGINE=InnoDB',
        'CREATE TABLE children_groups (child_id INT UNSIGNED, group_id INT UNSIGNED, PRIMARY KEY(child_id,group_id)) ENGINE=InnoDB',
        'CREATE TABLE teacher_groups (user_id INT UNSIGNED, group_id INT UNSIGNED, PRIMARY KEY(user_id,group_id)) ENGINE=InnoDB',
        'CREATE TABLE parameters (param_key VARCHAR(100) PRIMARY KEY, param_value TEXT) ENGINE=InnoDB',
        'CREATE TABLE email_template (template_html TEXT, subject TEXT) ENGINE=InnoDB',
        "CREATE TABLE messages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, child_id INT UNSIGNED, group_id INT UNSIGNED, message_date DATE, breakfast INT DEFAULT 0, lunch INT DEFAULT 0, mood INT DEFAULT 0, sleep_minutes INT DEFAULT 0, wc INT DEFAULT 0, activities TEXT, comments TEXT, email_status VARCHAR(20) DEFAULT 'pending', UNIQUE KEY (child_id,message_date)) ENGINE=InnoDB",
        'INSERT INTO users VALUES (1),(2)',
        "INSERT INTO children (id,first_name,last_name,email1) VALUES (1,'Test','A','a@example.invalid'),(2,'Test','B','b@example.invalid'),(3,'Test','C','c@example.invalid')",
        'INSERT INTO children_groups VALUES (1,1),(2,1),(3,2)',
        'INSERT INTO teacher_groups VALUES (2,1)',
        "INSERT INTO parameters VALUES ('email_mode','virtual')",
        "INSERT INTO email_template VALUES ('<p>|*name*| |*sleep*| λεπτά</p>', 'Test')",
    ] as $sql) $db->exec($sql);
    $scope = ['group_id' => '1', 'date' => '2026-09-23'];
    $r = callApi($name, 'list', $scope);
    check($r['status'] === 200 && $r['body']['attendance_ready'] === false, 'missing table: load available, attendance disabled');
    check(callApi($name, 'send', $scope)['status'] === 503, 'missing table: server blocks sending');
    check(callApi($name, 'migrate', ['backup_confirmed' => '1'], 'teacher')['status'] === 403, 'teacher cannot migrate');
    check(callApi($name, 'migrate', ['backup_confirmed' => '1'], 'parent')['status'] === 403, 'parent cannot migrate');
    check(callApi($name, 'migrate', ['backup_confirmed' => '1', '_token' => 'bad'])['status'] === 403, 'migration CSRF enforced');
    check(callApi($name, 'migrate')['status'] === 422, 'backup acknowledgement required');
    check(callApi($name, 'migrate', ['backup_confirmed' => '1'])['body']['success'] === true, 'admin migration succeeds');
    check(callApi($name, 'migrate', ['backup_confirmed' => '1'])['body']['success'] === true, 'repeated migration succeeds without duplication');
    $attendance = new Attendance($db);
    check($attendance->isReady(), 'schema verified');
    $absent = $scope + ['child_id' => '1', 'is_absent' => '1'];
    check(callApi($name, 'attendance', $absent, 'parent')['status'] === 403, 'parent cannot change absence');
    check(callApi($name, 'attendance', array_replace($absent, ['_token' => 'bad']))['status'] === 403, 'attendance CSRF enforced');
    check(callApi($name, 'attendance', array_replace($absent, ['date' => '2026-02-30']))['status'] === 422, 'impossible date rejected');
    check(callApi($name, 'attendance', array_replace($absent, ['date' => ['2026-09-23']]))['status'] === 422, 'array date rejected');
    check(callApi($name, 'attendance', array_replace($absent, ['is_absent' => 'yes']))['status'] === 422, 'invalid absence value rejected');
    check(callApi($name, 'attendance', array_replace($absent, ['child_id' => '3']), 'teacher')['status'] === 403, 'child membership enforced');
    check(callApi($name, 'attendance', array_replace($absent, ['group_id' => '2', 'child_id' => '3']), 'teacher')['status'] === 403, 'teacher group access enforced');
    check(callApi($name, 'attendance', $absent, 'teacher')['body']['success'] === true, 'teacher saves absence');
    check($attendance->isAbsent(1, '2026-09-23'), 'absence persisted without message');
    check((int)$db->query('SELECT COUNT(*) FROM messages')->fetchColumn() === 0, 'attendance does not create empty messages');
    $r = callApi($name, 'list', $scope);
    check($r['body']['rows'][0]['is_absent'] === true, 'separate admin session reads teacher absence');
    check(callApi($name, 'list', array_replace($scope, ['date' => '2026-09-24']))['body']['rows'][0]['is_absent'] === false, 'next day defaults present');
    $rows = [];
    foreach ([1,2] as $id) $rows[] = ['child_id' => $id, 'breakfast' => 4, 'lunch' => 3, 'mood' => 4, 'sleep_minutes' => 0, 'wc' => '0', 'activities_txt' => 'test', 'comments' => 'preserve'];
    check(callApi($name, 'save', $scope + ['rows' => $rows])['body']['saved'] === 2, 'messages save with absent child');
    check($attendance->isAbsent(1, '2026-09-23'), 'message save preserves attendance');
    $r = callApi($name, 'send', $scope + ['excluded_child_ids' => []]);
    check($r['body']['mode'] === 'virtual' && $r['body']['sent'] === 1 && $r['body']['skipped'] === 1, 'server skips DB absence even with empty browser exclusions');
    check($db->query('SELECT email_status FROM messages WHERE child_id=1')->fetchColumn() === 'pending', 'skipped email keeps pending status');
    check(callApi($name, 'attendance', array_replace($absent, ['is_absent' => '0']))['body']['success'] === true, 'undo absence');
    check(!$attendance->isAbsent(1, '2026-09-23'), 'undo persisted');
    check($db->query('SELECT comments FROM messages WHERE child_id=1')->fetchColumn() === 'preserve', 'undo preserves comments');
    $r = callApi($name, 'send', $scope);
    check($r['body']['sent'] === 2 && $r['body']['skipped'] === 0, 'undo permits virtual send');
    callApi($name, 'attendance', $absent);
    callApi($name, 'attendance', array_replace($absent, ['child_id' => '2']));
    $r = callApi($name, 'send', $scope);
    check($r['body']['sent'] === 0 && $r['body']['skipped'] === 2, 'multiple absent children skipped');
    check(callApi($name, 'migrate', ['backup_confirmed' => '1'])['body']['success'] === true && count($attendance->absentIds('2026-09-23')) === 2, 'rerun migration preserves absences');
    $db->exec('RENAME TABLE child_attendance TO attendance_saved');
    $db->exec('CREATE TABLE child_attendance (sentinel INT)');
    $db->exec('INSERT INTO child_attendance VALUES (42)');
    check(callApi($name, 'migrate', ['backup_confirmed' => '1'])['status'] === 503, 'incompatible table migration refused');
    check((int)$db->query('SELECT sentinel FROM child_attendance')->fetchColumn() === 42, 'incompatible data untouched');
    check(callApi($name, 'send', $scope)['status'] === 503, 'incompatible schema blocks sends');
    $db->exec('DROP TABLE child_attendance');
    $db->exec('RENAME TABLE attendance_saved TO child_attendance');
    echo "All $checks checks passed; isolated database only; no real emails.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e instanceof PDOException ? "Local test database operation failed (credentials suppressed).\n" : $e->getMessage() . "\n");
    $exit = 1;
} finally {
    if ($created) {
        $server->exec("DROP DATABASE `$name`");
        echo "Isolated test database removed.\n";
    }
}
exit($exit);