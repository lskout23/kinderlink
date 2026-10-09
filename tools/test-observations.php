<?php
declare(strict_types=1);

/*
 * Isolated CLI test for the observations/comments split in daily reports.
 * Runs the REAL MessagesController::apiSave / apiListByGroup / apiSearch against an
 * in-memory SQLite database with fictional rows. Auth/CSRF/JSON output are stubbed.
 * No configuration, MySQL, network, mail or photo storage is touched.
 * Usage: php tools/test-observations.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);

class Controller {
    public array $responses = [];
    public function __construct(protected PDO $db) {}
    protected function json(mixed $data, int $status = 200): void {
        $this->responses[] = ['status' => $status, 'data' => $data];
    }
    protected function verifyCsrf(): void {}
}

final class Auth {
    public static function requireRole(string ...$roles): void {}
    public static function requireLogin(): void {}
    public static function isAdmin(): bool { return true; }
    public static function isTeacher(): bool { return false; }
    public static function user(): array { return ['id' => 1, 'role' => 'admin']; }
}

require $root . '/models/Attendance.php';
require $root . '/controllers/MessagesController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE children (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, active INTEGER)');
$db->exec('CREATE TABLE children_groups (child_id INTEGER, group_id INTEGER)');
$db->exec('CREATE TABLE "groups" (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec('CREATE TABLE messages (id INTEGER PRIMARY KEY AUTOINCREMENT, child_id INTEGER, group_id INTEGER,
    message_date TEXT, breakfast INTEGER, lunch INTEGER, mood INTEGER, sleep_minutes INTEGER, wc INTEGER,
    activities TEXT, comments TEXT, email_status TEXT)');
$db->exec("INSERT INTO \"groups\" VALUES (1, 'Test group')");
foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'Gamma'] as $id => $name) {
    $db->exec("INSERT INTO children VALUES ($id, 'Child', '$name', 1)");
    $db->exec("INSERT INTO children_groups VALUES ($id, 1)");
}
$date = '2026-10-09';

$passed = 0; $failed = 0;
function check(string $name, mixed $actual, mixed $expected): void {
    global $passed, $failed;
    if ($actual === $expected) { $passed++; echo "PASS $name\n"; return; }
    $failed++;
    echo "FAIL $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}
function controller(PDO $db): MessagesController { return new MessagesController($db); }
function save(PDO $db, array $rows): array {
    global $date;
    $_POST = ['group_id' => '1', 'date' => $date, 'rows' => $rows];
    $c = controller($db);
    $c->apiSave();
    return end($c->responses);
}
function listed(PDO $db): array {
    global $date;
    $_POST = ['group_id' => '1', 'date' => $date];
    $c = controller($db);
    $c->apiListByGroup();
    $rows = [];
    foreach (end($c->responses)['data']['rows'] as $row) $rows[(int)$row['id']] = $row;
    return $rows;
}
function stored(PDO $db, int $childId): ?string {
    $v = $db->query("SELECT comments FROM messages WHERE child_id = $childId")->fetchColumn();
    return $v === false ? null : $v;
}
$base = ['breakfast' => '4', 'lunch' => '3', 'mood' => '4', 'sleep_minutes' => '30', 'wc' => '1'];

// 1. Web save with all three fields.
$r = save($db, [$base + ['child_id' => '1', 'activities_txt' => 'Ζωγραφική, Μουσική',
    'observations_txt' => 'Σήμερα κάναμε άσκηση σεισμού!/ Today at school, we had an earthquake drill!',
    'comments' => 'Έφαγε όλο το φαγητό']]);
check('web save succeeds', $r['data']['success'] ?? null, true);
$rows = listed($db);
check('list: activities unchanged', $rows[1]['activities_txt'], 'Ζωγραφική, Μουσική');
check('list: observations separate (comma inside item kept)', $rows[1]['observations_txt'],
    'Σήμερα κάναμε άσκηση σεισμού!/ Today at school, we had an earthquake drill!');
check('list: comments separate', $rows[1]['comments'], 'Έφαγε όλο το φαγητό');
check('list: packed storage not exposed', array_key_exists('notes_blob', $rows[1]), false);

// 2. Older mobile app build: sends comments but NOT observations_txt -> observations preserved.
save($db, [$base + ['child_id' => '1', 'activities_txt' => 'Ζωγραφική', 'comments' => 'Νέο σχόλιο από κινητό']]);
$rows = listed($db);
check('old client keeps saved observations', $rows[1]['observations_txt'],
    'Σήμερα κάναμε άσκηση σεισμού!/ Today at school, we had an earthquake drill!');
check('old client updates comments', $rows[1]['comments'], 'Νέο σχόλιο από κινητό');
check('old client updates activities', $rows[1]['activities_txt'], 'Ζωγραφική');

// 3. Web/new client sends an EMPTY observations field -> cleared on purpose.
save($db, [$base + ['child_id' => '1', 'activities_txt' => '', 'observations_txt' => '', 'comments' => 'Μόνο σχόλιο']]);
$rows = listed($db);
check('explicit empty observations clears them', $rows[1]['observations_txt'], '');
check('comments kept when observations cleared', $rows[1]['comments'], 'Μόνο σχόλιο');

// 4. Old client, first save of the day (no existing row) -> no observations invented.
save($db, [$base + ['child_id' => '2', 'activities_txt' => 'Αυλή', 'comments' => 'Πρώτη αποθήκευση']]);
$rows = listed($db);
check('old client new row: observations empty', $rows[2]['observations_txt'], '');
check('old client new row: comments saved', $rows[2]['comments'], 'Πρώτη αποθήκευση');

// 5. Legacy row stored before the split (plain text) reads as observations, then survives an old-client save.
$db->exec("INSERT INTO messages (child_id, group_id, message_date, breakfast, lunch, mood, sleep_minutes, wc, activities, comments, email_status)
    VALUES (3, 1, '$date', 0, 0, 0, 0, 0, 'Παλιό', 'Παλιό κείμενο παρατήρησης', 'sent')");
$rows = listed($db);
check('legacy text reads as observations', $rows[3]['observations_txt'], 'Παλιό κείμενο παρατήρησης');
check('legacy text has no comments', $rows[3]['comments'], '');
save($db, [$base + ['child_id' => '3', 'activities_txt' => 'Παλιό', 'comments' => 'Προσθήκη']]);
$rows = listed($db);
check('legacy observations survive old-client save', $rows[3]['observations_txt'], 'Παλιό κείμενο παρατήρησης');
check('legacy row gets new comments', $rows[3]['comments'], 'Προσθήκη');

// 6. Both empty -> stored as empty string (no packed marker).
save($db, [$base + ['child_id' => '2', 'activities_txt' => '', 'observations_txt' => '', 'comments' => '']]);
check('both empty stored as empty string', stored($db, 2), '');

// 7. Search API splits the fields for the message list.
$_POST = ['group_id' => '1', 'from' => $date, 'to' => $date];
$c = controller($db);
$c->apiSearch();
$search = [];
foreach (end($c->responses)['data']['rows'] as $row) $search[$row['last_name']] = $row;
check('search: observations split', $search['Gamma']['observations'], 'Παλιό κείμενο παρατήρησης');
check('search: comments split', $search['Gamma']['comments'], 'Προσθήκη');

// 8. Email helper: empty -> "-", otherwise unchanged.
$m = new ReflectionMethod(MessagesController::class, 'dashIfEmpty');
$m->setAccessible(true);
$dash = controller($db);
check('dashIfEmpty null', $m->invoke($dash, null), '-');
check('dashIfEmpty spaces', $m->invoke($dash, '   '), '-');
check('dashIfEmpty text', $m->invoke($dash, 'Ζωγραφική'), 'Ζωγραφική');
check('dashIfEmpty zero string', $m->invoke($dash, '0'), '0');

echo "\nRESULT: " . ($passed + $failed) . " checks, $passed passed, $failed failed.\n";
echo "UNCOVERED: real Auth/session/CSRF, MySQL-specific behaviour, email sending, browser/mobile UI rendering.\n";
exit($failed === 0 ? 0 : 1);
