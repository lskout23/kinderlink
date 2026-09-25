<?php
declare(strict_types=1);

// Isolated tests: no app config, network, real database, mail or school photos.
// Load the actual controller in a test namespace for deterministic filesystem
// faults. Successful filesystem calls use real, newly created temporary files.
namespace PhotoDeletionQA;

use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

final class Env {
    public static array $unlinkFailures = [];
    public static array $statFailures = [];
    public static array $statModes = [];
    public static array $concurrentRemovals = [];
    public static bool $listFailure = false;
    public static bool $roleAllowed = true;
    public static bool $csrfAllowed = true;
    public static bool $admin = true;
    public static int $unlinkCalls = 0;
}
function unlink(string $path): bool {
    Env::$unlinkCalls++;
    if (in_array(basename($path), Env::$concurrentRemovals, true)) {
        \unlink($path);
        return false;
    }
    return in_array(basename($path), Env::$unlinkFailures, true) ? false : \unlink($path);
}
function lstat(string $path): array|false {
    if (isset(Env::$statModes[basename($path)])) { return ['mode' => Env::$statModes[basename($path)]]; }
    return in_array(basename($path), Env::$statFailures, true) ? false : \lstat($path);
}
function move_uploaded_file(string $source, string $destination): bool {
    // CLI synthetic upload only; real HTTP upload validation is not exercised.
    return \copy($source, $destination);
}
function scandir(string $path): array|false {
    return Env::$listFailure ? false : \scandir($path);
}
final class Response extends RuntimeException {
    public function __construct(public array $data, public int $status) { parent::__construct('response'); }
}
class Controller {
    public function __construct(public FakeDb $db) {}
    protected function verifyCsrf(): void {
        if (!Env::$csrfAllowed) { throw new Response(['error' => 'CSRF'], 403); }
    }
    protected function json(mixed $data, int $status = 200): void { throw new Response($data, $status); }
}
class Auth {
    public static function requireRole(string ...$roles): void {
        if (!Env::$roleAllowed) { throw new Response(['error' => 'role'], 403); }
    }
    public static function isAdmin(): bool { return Env::$admin; }
    public static function isTeacher(): bool { return !Env::$admin; }
    public static function user(): array { return ['id' => 1]; }
}
final class FakeDb {
    public array $rows = [];
    public array $executed = [];
    public string $prepareFailure = '';
    public array $executeFailures = [];
    public bool $teacherAccess = true;
    public int $insertId = 100;
    public function lastInsertId(): string { return (string)$this->insertId; }
    public function prepare(string $sql): FakeStatement|false {
        if (str_starts_with($sql, 'DELETE')) {
            if ($this->prepareFailure === 'throw') { throw new RuntimeException('private DB detail'); }
            if ($this->prepareFailure === 'false') { return false; }
        }
        return new FakeStatement($this, $sql);
    }
    public function query(string $sql): FakeStatement {
        $s = new FakeStatement($this, $sql); $s->execute([]); return $s;
    }
}
final class FakeStatement {
    private array $result = [];
    private int $affected = 0;
    public function __construct(private FakeDb $db, private string $sql) {}
    public function execute(array $params = []): bool {
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));
        $this->db->executed[] = [$sql, $params];
        if (str_starts_with($sql, 'INSERT INTO message_photos')) {
            $id = ++$this->db->insertId;
            $this->db->rows[$id] = ['id' => $id, 'child_id' => $params[0], 'group_id' => $params[1],
                'message_date' => $params[2], 'stored_name' => $params[3], 'original_name' => $params[4],
                'hidden_at' => null, 'expired' => false];
            return true;
        }
        if (str_starts_with($sql, 'DELETE')) {
            if ($sql !== 'DELETE FROM message_photos WHERE id = ? AND stored_name = ?') {
                throw new RuntimeException('Unsafe/unexpected DELETE: ' . $sql);
            }
            [$id, $name] = $params;
            $fault = $this->db->executeFailures[$id] ?? '';
            if ($fault === 'throw') { throw new RuntimeException('private DB detail'); }
            if ($fault === 'false') { return false; }
            $this->affected = isset($this->db->rows[$id]) && $this->db->rows[$id]['stored_name'] === $name ? 1 : 0;
            if ($this->affected) { unset($this->db->rows[$id]); }
            return true;
        }
        if (str_contains($sql, 'FROM teacher_groups')) {
            $this->result = $this->db->teacherAccess ? [[1]] : []; return true;
        }
        if (str_contains($sql, 'FROM children_groups')) {
            $this->result = [[1]]; return true;
        }
        if (str_contains($sql, 'FROM children c')) {
            $this->result = [['id' => 1, 'first_name' => 'Test', 'last_name' => 'Fixture']]; return true;
        }
        if (!str_contains($sql, 'FROM message_photos')) {
            throw new RuntimeException('Unexpected query: ' . $sql);
        }
        $rows = array_values($this->db->rows);
        if (str_contains($sql, 'created_at <')) {
            $rows = array_filter($rows, fn($r) => $r['expired']);
        } elseif (str_contains($sql, 'original_name IN')) {
            $group = str_contains($sql, 'WHERE child_id =') ? $params[1] : $params[0];
            $rows = array_filter($rows, fn($r) => $r['group_id'] === $group && $r['child_id'] === 1 && in_array($r['original_name'], $params, true));
        } elseif (str_contains($sql, 'WHERE group_id = ? AND message_date = ?')) {
            $rows = array_filter($rows, fn($r) => $r['group_id'] === $params[0] && $r['message_date'] === $params[1]
                && (!isset($params[2]) || $r['child_id'] === $params[2]));
        } elseif (str_contains($sql, 'WHERE id IN')) {
            $rows = array_filter($rows, fn($r) => in_array($r['id'], $params, true));
        } elseif (str_contains($sql, 'WHERE id = ?')) {
            $rows = array_filter($rows, fn($r) => $r['id'] === $params[0]);
        } elseif ($sql !== 'SELECT id, stored_name FROM message_photos') {
            throw new RuntimeException('Unhandled selection: ' . $sql);
        }
        $this->result = array_values($rows); return true;
    }
    public function fetchAll(): array { return $this->result; }
    public function fetch(): array|false { return $this->result[0] ?? false; }
    public function fetchColumn(): mixed { return $this->result ? array_values($this->result[0])[0] : false; }
    public function rowCount(): int { return $this->affected; }
}

$source = realpath($argv[1] ?? (__DIR__ . '/../controllers/MessagesController.php'));
if (!$source) { throw new RuntimeException('Controller not found'); }
$root = sys_get_temp_dir() . '/kinderlink-photo-delete-qa-' . bin2hex(random_bytes(8));
mkdir($root . '/storage/message_photos', 0700, true);
define('ROOT', $root);
define('BASE_URL', '');
define('APP_URL', 'http://localhost.invalid');
ini_set('error_log', $root . '/test-errors.log');
function removeTestTree(string $path): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (\scandir($path) as $name) {
            if ($name !== '.' && $name !== '..') { removeTestTree($path . '/' . $name); }
        }
        rmdir($path);
    } else { \unlink($path); }
}
register_shutdown_function(static function() use ($root) { removeTestTree($root); });
// Namespace-only shim; method bodies are evaluated unchanged from the target.
$code = file_get_contents($source);
if (str_starts_with($code, "\xEF\xBB\xBF")) { $code = substr($code, 3); }
if (!str_starts_with($code, '<?php')) { throw new RuntimeException('Unexpected PHP header'); }
eval('namespace PhotoDeletionQA; use \\Throwable; use \\PDO; ' . substr($code, 5));

$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++;
}
function invoke(object $controller, string $method, mixed ...$args): mixed {
    return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$args);
}
function fixture(): array {
    Env::$unlinkFailures = Env::$statFailures = Env::$statModes = Env::$concurrentRemovals = [];
    Env::$listFailure = false; Env::$unlinkCalls = 0;
    Env::$roleAllowed = Env::$csrfAllowed = Env::$admin = true;
    $_POST = []; $_FILES = [];
    foreach (\scandir(ROOT . '/storage/message_photos') as $name) {
        if ($name !== '.' && $name !== '..') { removeTestTree(ROOT . '/storage/message_photos/' . $name); }
    }
    $db = new FakeDb(); $c = new MessagesController($db);
    foreach (['hasPhotosTable' => true, 'photoRetentionDays' => 180, 'photoHiddenGraceDays' => 15] as $name => $value) {
        (new \ReflectionProperty($c, $name))->setValue($c, $value);
    }
    return [$c, $db];
}
function add(FakeDb $db, int $id, string $name, bool $file = true, array $extra = []): array {
    $row = array_merge(['id' => $id, 'stored_name' => $name, 'original_name' => 'fixture.png', 'child_id' => 1,
        'group_id' => 1, 'message_date' => '2026-09-25', 'hidden_at' => null, 'expired' => false], $extra);
    $db->rows[$id] = $row;
    if ($file) { file_put_contents(ROOT . '/storage/message_photos/' . $name, 'synthetic fixture'); }
    return $row;
}
function response(object $c, string $method): Response {
    try { $c->$method(); } catch (Response $r) { return $r; }
    throw new RuntimeException('Missing JSON response: ' . $method);
}
function purge(object $c, FakeDb $db): array { return invoke($c, 'purgePhotoRows', array_values($db->rows)); }

[$c, $db] = fixture();
add($db, 1, 'ok.png'); add($db, 2, 'absent.png', false); add($db, 3, 'denied.png');
Env::$unlinkFailures = ['denied.png'];
$r = purge($c, $db);
check($r === ['purged' => 2, 'deleted_records' => 2, 'deleted_files' => 1, 'failed' => 1], 'mixed result counts');
check(array_keys($db->rows) === [3], 'retain only failed metadata');
check(is_file(ROOT . '/storage/message_photos/denied.png'), 'retain failed file');
check(!is_file(ROOT . '/storage/message_photos/ok.png'), 'physical success removed');
Env::$unlinkFailures = [];
check(purge($c, $db)['purged'] === 1 && !$db->rows, 'successful retry');
check(purge($c, $db)['purged'] === 0, 'empty retry idempotent');

foreach (['../outside.png', '..\\outside.png', '/absolute', 'C:drive', '', '.', '..', "null\0.png"] as $name) {
    [$c, $db] = fixture(); add($db, 1, $name, false);
    check(purge($c, $db)['failed'] === 1 && isset($db->rows[1]) && Env::$unlinkCalls === 0, 'reject unsafe name');
}
[$c, $db] = fixture(); add($db, 1, 'directory.png', false); mkdir(ROOT . '/storage/message_photos/directory.png');
check(purge($c, $db)['failed'] === 1 && Env::$unlinkCalls === 0, 'directory is not missing file');
foreach ([0120000, 0010000] as $mode) {
    [$c, $db] = fixture(); add($db, 1, 'special.png'); Env::$statModes = ['special.png' => $mode];
    check(purge($c, $db)['failed'] === 1 && Env::$unlinkCalls === 0 && isset($db->rows[1]), 'reject simulated symlink/FIFO');
}
[$c, $db] = fixture(); add($db, 1, 'denied-stat.png'); Env::$statFailures = ['denied-stat.png'];
check(purge($c, $db)['failed'] === 1 && isset($db->rows[1]), 'stat failure with listed file keeps row');
[$c, $db] = fixture(); add($db, 1, 'missing.png', false); Env::$listFailure = true;
check(purge($c, $db)['failed'] === 1 && isset($db->rows[1]), 'unreadable directory is not absence');
[$c, $db] = fixture(); add($db, 1, 'race.png'); Env::$concurrentRemovals = ['race.png'];
check(purge($c, $db)['purged'] === 1 && !$db->rows, 'concurrent removal is safe to complete');
foreach (['throw', 'false'] as $fault) {
    [$c, $db] = fixture(); add($db, 1, 'prepare.png'); $db->prepareFailure = $fault;
    check(purge($c, $db)['failed'] === 1 && Env::$unlinkCalls === 0 && isset($db->rows[1]), 'prepare failure no filesystem change');
    [$c, $db] = fixture(); add($db, 1, 'execute.png'); $db->executeFailures = [1 => $fault];
    $r = purge($c, $db);
    check($r['failed'] === 1 && $r['deleted_files'] === 1 && isset($db->rows[1]), 'DB execute failure retains row');
    $db->executeFailures = [];
    check(purge($c, $db)['purged'] === 1 && !$db->rows, 'DB failure retry handles now-missing file');
}

foreach (['apiPhotosPurge', 'apiPhotosPurgeSelected', 'apiPhotosPurgeDay', 'apiPurgeAllPhotos'] as $method) {
    foreach ([false, true] as $fail) {
        [$c, $db] = fixture(); add($db, 1, 'one.png');
        $_POST = ['id' => 1, 'ids' => [1, 2], 'group_id' => 1, 'date' => '2026-09-25'];
        if ($method !== 'apiPhotosPurge') { add($db, 2, 'two.png', false); }
        if ($fail) { Env::$unlinkFailures = ['one.png']; }
        $r = response($c, $method);
        check($r->status === ($fail ? 500 : 200), $method . ' status');
        check($r->data['success'] === !$fail, $method . ' success');
        check($r->data['failed'] === (int)$fail, $method . ' failed count');
        check($r->data['purged'] === ($method === 'apiPhotosPurge' ? 1 : 2) - (int)$fail, $method . ' purged count');
        check(isset($db->rows[1]) === $fail, $method . ' metadata retention');
        if ($fail) {
            check($r->data['error_code'] === 'PHOTO_DELETE_FAILED', $method . ' error code');
            check(str_contains($r->data['error'], (string)$r->data['purged']) && str_contains($r->data['error'], '1'), $method . ' UI-ready counts');
            check(!str_contains(json_encode($r->data), 'one.png') && !str_contains(json_encode($r->data), ROOT), $method . ' no private paths/names');
        }
    }
    foreach (['role', 'csrf'] as $guard) {
        [$c, $db] = fixture(); add($db, 1, 'protected.png');
        Env::$roleAllowed = $guard !== 'role'; Env::$csrfAllowed = $guard !== 'csrf';
        check(response($c, $method)->status === 403 && Env::$unlinkCalls === 0 && !$db->executed, $method . ' guard first');
    }
}

[$c, $db] = fixture();
add($db, 1, 'target.png'); add($db, 2, 'hidden.png', true, ['hidden_at' => '2026-09-25 08:00:00']);
add($db, 3, 'other-child.png', true, ['child_id' => 2]);
add($db, 4, 'other-group.png', true, ['group_id' => 2]);
add($db, 5, 'other-date.png', true, ['message_date' => '2026-09-24']);
$_POST = ['group_id' => 1, 'child_id' => 1, 'date' => '2026-09-25'];
check(response($c, 'apiPhotosPurgeDay')->data['purged'] === 2, 'child-filtered purge includes hidden');
check(array_keys($db->rows) === [3, 4, 5], 'day/child/group boundaries preserved');
unset($_POST['child_id']);
check(response($c, 'apiPhotosPurgeDay')->data['purged'] === 1, 'Clear entire group/day remaining children');
check(array_keys($db->rows) === [4, 5], 'Clear preserves other dates/groups');
[$c, $db] = fixture(); add($db, 1, 'not-yours.png'); Env::$admin = false; $db->teacherAccess = false;
$_POST = ['group_id' => 1, 'date' => '2026-09-25'];
check(response($c, 'apiPhotosPurgeDay')->status === 403 && Env::$unlinkCalls === 0, 'teacher group access guard');

[$c, $db] = fixture();
add($db, 1, 'expired-ok.png', true, ['expired' => true]);
add($db, 2, 'expired-denied.png', true, ['expired' => true]);
add($db, 3, 'active.png');
Env::$unlinkFailures = ['expired-denied.png'];
invoke($c, 'cleanupOldPhotos');
check(array_keys($db->rows) === [2, 3], 'cleanup retains failed and active rows');
check(is_file(ROOT . '/storage/message_photos/active.png'), 'cleanup leaves active file');
Env::$unlinkFailures = []; invoke($c, 'cleanupOldPhotos');
check(array_keys($db->rows) === [3], 'cleanup retries retained expired rows');

// Both replacement endpoints must stop before storing any new upload on failure.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6GAAAAABJRU5ErkJggg==');
file_put_contents(ROOT . '/incoming.png', $png);
foreach (['apiPhotosUpload', 'apiPhotosUploadGroup'] as $method) {
    [$c, $db] = fixture(); add($db, 1, 'old-ok.png'); add($db, 2, 'old-denied.png');
    Env::$unlinkFailures = ['old-denied.png'];
    $_POST = ['group_id' => 1, 'child_id' => 1, 'date' => '2026-09-25', 'force_overwrite' => 1];
    $_FILES = ['photos' => ['name' => 'fixture.png', 'tmp_name' => ROOT . '/incoming.png', 'size' => strlen($png), 'error' => UPLOAD_ERR_OK]];
    $r = response($c, $method);
    check($r->status === 500 && $r->data['error_code'] === 'PHOTO_DELETE_FAILED', $method . ' replacement fails clearly');
    check($r->data['purged'] === 1 && $r->data['failed'] === 1, $method . ' partial replacement counts');
    check(array_keys($db->rows) === [2], $method . ' failed old record retained');
    check(count(\scandir(ROOT . '/storage/message_photos')) === 3, $method . ' no new file stored');
    check(str_contains($r->data['error'], 'πριν αποθηκευτούν νέες'), $method . ' explicit no-new-upload message');
}
foreach (['apiPhotosUpload', 'apiPhotosUploadGroup'] as $method) {
    foreach (['replace', 'skip', 'prompt'] as $mode) {
        [$c, $db] = fixture(); add($db, 1, 'original.png');
        $_POST = ['group_id' => 1, 'child_id' => 1, 'date' => '2026-09-25',
            'force_overwrite' => (int)($mode === 'replace'), 'skip_duplicates' => (int)($mode === 'skip')];
        $_FILES = ['photos' => ['name' => 'fixture.png', 'tmp_name' => ROOT . '/incoming.png', 'size' => strlen($png), 'error' => UPLOAD_ERR_OK]];
        $r = response($c, $method);
        if ($mode === 'replace') {
            check($r->status === 200 && $r->data['saved'] === 1, $method . ' replacement success');
            check(!isset($db->rows[1]) && count($db->rows) === 1, $method . ' replaced metadata');
            check(!is_file(ROOT . '/storage/message_photos/original.png') && count(\scandir(ROOT . '/storage/message_photos')) === 3, $method . ' replaced physical file');
        } else {
            check(isset($db->rows[1]) && Env::$unlinkCalls === 0, $method . ' skip/prompt preserve original');
            check($r->status === ($mode === 'skip' ? 200 : 409), $method . ' skip/prompt status');
        }
    }
}
echo "PASS: $checks assertions; controller=" . $source . "; isolated files + mocked DB/faults only.\n";