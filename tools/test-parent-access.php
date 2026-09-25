<?php
declare(strict_types=1);

/**
 * CLI regression tests for explicit parent/child links (emails never authorize).
 * Usage: php tools/test-parent-access.php [source-root]
 * Default root: dirname(__DIR__). A root needs only the two controller files.
 * Exit 0 = all pass, 1 = regressions, 2 = prerequisites/harness impossible.
 * No bootstrap, config, real database, filesystem fixtures, or real mail.
 * SQL and controller method bodies are NOT rewritten or mocked.
 */
namespace ParentAccessQA;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

set_error_handler(static function(int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) { return false; }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

final class Response extends RuntimeException {
    public function __construct(public array $data, public int $status = 200) {
        parent::__construct('Captured JSON response');
    }
}

class Controller {
    public ?array $rendered = null;
    public function __construct(protected PDO $db) {}
    protected function json(array $data, int $status = 200): void {
        throw new Response($data, $status); // Actual public endpoint cannot fall through/exit.
    }
    protected function render(string $view, array $data = []): void {
        $this->rendered = ['view' => $view, 'data' => $data];
    }
    protected function verifyCsrf(): void {
        // Deliberately valid: exercise authorization, not session/CSRF infrastructure.
    }
}

final class Auth {
    public static array $current = [];
    public static function user(): array { return self::$current; }
    public static function requireLogin(): void {
        if (!self::$current) { throw new Response(['error' => 'Login required'], 401); }
    }
    public static function requireRole(string ...$roles): void {
        self::requireLogin();
        if (!in_array(self::$current['role'], $roles, true)) {
            throw new Response(['error' => 'Role denied'], 403);
        }
    }
}

final class Mailer {
    public static array $sent = [];
    public function send(array $to, string $subject, string $html): bool {
        self::$sent[] = compact('to', 'subject', 'html');
        return true;
    }
}

// Instrumentation only: every query is executed by real SQLite PDO. Record SQL
// errors even when countUnread() catches them, and attempted no-op writes too.
final class SqlTrace {
    public static array $errors = [];
    public static array $writes = [];
    public static function record(string $sql): void {
        if (!preg_match('/^\s*(SELECT|EXPLAIN)\b/i', $sql)) { self::$writes[] = $sql; }
    }
}
final class TracedStatement extends PDOStatement {
    protected function __construct() {}
    public function execute(?array $params = null): bool {
        SqlTrace::record($this->queryString);
        try { return parent::execute($params); }
        catch (Throwable $e) { SqlTrace::$errors[] = $e->getMessage(); throw $e; }
    }
}
final class MemoryPDO extends PDO {
    public function __construct() {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STATEMENT_CLASS => [TracedStatement::class],
        ]);
        $this->sqliteCreateFunction('NOW', static fn() => '2026-09-25 12:00:00', 0);
        $this->sqliteCreateFunction('CURDATE', static fn() => '2026-09-25', 0);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        try { return parent::prepare($query, $options); }
        catch (Throwable $e) { SqlTrace::$errors[] = $e->getMessage(); throw $e; }
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        SqlTrace::record($query);
        try { return parent::query($query, $fetchMode, ...$fetchModeArgs); }
        catch (Throwable $e) { SqlTrace::$errors[] = $e->getMessage(); throw $e; }
    }
    public function exec(string $statement): int|false {
        SqlTrace::record($statement);
        try { return parent::exec($statement); }
        catch (Throwable $e) { SqlTrace::$errors[] = $e->getMessage(); throw $e; }
    }
}

function loadController(string $root, string $name): void {
    $file = $root . '/controllers/' . $name . '.php';
    if (!is_file($file) || !is_readable($file)) { throw new RuntimeException('Missing controller: ' . $file); }
    $source = file_get_contents($file);
    if ($source === false) { throw new RuntimeException('Cannot read ' . $file); }
    $source = preg_replace('/^\xEF\xBB\xBF/', '', $source);
    if (!str_starts_with($source, '<?php')) { throw new RuntimeException('Unsupported PHP header: ' . $file); }

    // Refuse bootstraps, exit paths, namespaces, and code outside the sole class.
    // This is a fixture-loader guard, not a sandbox for untrusted PHP sources.
    $tokens = token_get_all($source, TOKEN_PARSE);
    $depth = 0;
    $opened = $closed = false;
    $prefix = '';
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_CLOSE_TAG], true)) { continue; }
            if (in_array($token[0], [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_EXIT, T_EVAL, T_NAMESPACE], true)) {
                throw new RuntimeException('Unsafe/unsupported loader token in ' . $file);
            }
            if (in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) { ++$depth; }
            $text = $token[1];
        } else { $text = $token; }
        if ($closed) { throw new RuntimeException('Code outside controller class: ' . $file); }
        if (!$opened) {
            if ($text !== '{') { $prefix .= $text; continue; }
            if ($prefix !== 'class' . $name . 'extendsController') {
                throw new RuntimeException('Unsupported controller declaration: ' . $file);
            }
            $opened = true;
        }
        if ($token === '{') { ++$depth; }
        if ($token === '}' && --$depth === 0) { $closed = true; }
    }
    if (!$opened || !$closed) { throw new RuntimeException('Incomplete controller: ' . $file); }
    // Only namespace/import context changes. Keep all methods and SQL verbatim.
    eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\Throwable; ' . substr($source, 5));
    printf("SOURCE %s sha256=%s\n", $file, hash('sha256', $source));
}

const TABLES = ['users', 'children', 'groups', 'children_groups', 'teacher_groups',
    'messages', 'parent_threads', 'parent_thread_messages'];
const BODY = 'Fictional regression message';

function fixture(array $options = []): array {
    $o = array_replace([
        'link' => 1, 'email1' => 'contact@example.invalid', 'email2' => 'second@example.invalid',
        'email' => 'parent@example.invalid', 'active' => 1, 'owner' => 1,
        'userActive' => 1, 'userRole' => 'parent',
    ], $options);
    $db = new MemoryPDO();
    $db->exec(<<<'SQL'
CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT, active INTEGER);
CREATE TABLE children (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT,
    parent_user_id INTEGER, email1 TEXT, email2 TEXT, active INTEGER);
CREATE TABLE `groups` (id INTEGER PRIMARY KEY, name TEXT, is_current INTEGER);
CREATE TABLE children_groups (child_id INTEGER, group_id INTEGER);
CREATE TABLE teacher_groups (user_id INTEGER, group_id INTEGER);
CREATE TABLE messages (id INTEGER PRIMARY KEY, child_id INTEGER, group_id INTEGER,
    message_date TEXT, body TEXT);
CREATE TABLE parent_threads (id INTEGER PRIMARY KEY AUTOINCREMENT, child_id INTEGER,
    parent_user_id INTEGER, group_id INTEGER, subject TEXT,
    created_at TEXT DEFAULT '2026-09-25 09:00:00', updated_at TEXT DEFAULT '2026-09-25 09:00:00');
CREATE TABLE parent_thread_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id INTEGER,
    sender_id INTEGER, body TEXT, created_at TEXT DEFAULT '2026-09-25 09:00:00', read_at TEXT);
INSERT INTO users VALUES (2,'Fictional Teacher','teacher@example.invalid','teacher',1),
    (3,'Other Parent','other@example.invalid','parent',1),
    (4,'Fictional Admin','admin@example.invalid','admin',1),
    (5,'Other Teacher','outsider@example.invalid','teacher',1);
INSERT INTO `groups` VALUES (1,'Fictional Group',1),(2,'Other Group',1);
INSERT INTO children_groups VALUES (1,1);
INSERT INTO teacher_groups VALUES (2,1),(5,2);
INSERT INTO messages VALUES (1,1,1,'2026-09-25','Fictional daily message');
INSERT INTO parent_thread_messages (id,thread_id,sender_id,body) VALUES
    (1,1,1,'Fictional parent history'),(2,1,2,'Fictional teacher history');
SQL);
    $db->prepare('INSERT INTO users VALUES (1,?,?,?,?)')->execute([
        'Fictional Parent', $o['email'], $o['userRole'], $o['userActive'],
    ]);
    $db->prepare('INSERT INTO children VALUES (1,?,?,?,?,?,?)')->execute([
        'Fictional', 'Child', $o['link'], $o['email1'], $o['email2'], $o['active'],
    ]);
    $db->prepare('INSERT INTO parent_threads (id,child_id,parent_user_id,group_id,subject) VALUES (1,1,?,1,?)')
        ->execute([$o['owner'], 'Fictional subject']);
    login($db, 1);
    Mailer::$sent = SqlTrace::$writes = SqlTrace::$errors = [];
    $_POST = $_GET = [];
    return [$db, new ParentController($db), new InboxController($db)];
}

function login(PDO $db, int $id): void {
    $s = $db->prepare('SELECT * FROM users WHERE id=?');
    $s->execute([$id]);
    Auth::$current = $s->fetch();
}
function response(object $controller, string $method, array $post = []): Response {
    $_POST = $post;
    try { $controller->$method(); } catch (Response $r) { return $r; }
    throw new RuntimeException('Missing JSON response: ' . $method);
}
function snapshot(PDO $db): array {
    $rows = [];
    foreach (TABLES as $table) { $rows[$table] = $db->query('SELECT * FROM `' . $table . '` ORDER BY rowid')->fetchAll(); }
    return [$rows, (int)$db->query('SELECT total_changes()')->fetchColumn(), count(SqlTrace::$writes)];
}
function ids(array $rows, string $key = 'id'): array {
    $ids = array_map('intval', array_column($rows, $key));
    sort($ids);
    return $ids;
}

$cases = $passed = $assertions = $failedAssertions = 0;
$caseFailures = [];
function check(bool $condition, string $label): void {
    global $assertions, $failedAssertions, $caseFailures;
    ++$assertions;
    if (!$condition) { ++$failedAssertions; $caseFailures[] = $label; }
}
function test(string $label, callable $body): void {
    global $cases, $passed, $caseFailures;
    ++$cases;
    $caseFailures = [];
    SqlTrace::$errors = [];
    try { $body(); }
    catch (Throwable $e) { $caseFailures[] = get_class($e) . ': ' . $e->getMessage(); }
    check(SqlTrace::$errors === [], 'SQL errors (including swallowed exceptions): ' . implode('; ', SqlTrace::$errors));
    if (!$caseFailures) { ++$passed; echo "PASS $label\n"; }
    else { echo "FAIL $label\n  " . implode("\n  ", $caseFailures) . "\n"; }
}
function noWrites(PDO $db, array $before): void {
    check(snapshot($db) === $before, 'No writes: all rows, total_changes(), and attempted write statements unchanged');
}
function denied(PDO $db, object $c, string $method, array $post): void {
    // Check effects BEFORE rollback, then isolate subsequent checks even when
    // testing a vulnerable copy that actually performs the forbidden deletion.
    $db->beginTransaction();
    try {
        $before = snapshot($db);
        $mail = Mailer::$sent;
        $r = response($c, $method, $post);
        check($r->status === 403 && isset($r->data['error']), 'Expected authorization 403, got ' . $r->status);
        noWrites($db, $before);
        check(Mailer::$sent === $mail, 'Denied request must not notify anyone');
    } finally {
        $db->rollBack();
    }
}
function success(Response $r): void {
    check($r->status === 200 && ($r->data['success'] ?? false) === true, 'Expected successful JSON response');
}
function checkMail(string $email): void {
    check(count(Mailer::$sent) === 1, 'Exactly one stub notification');
    check((Mailer::$sent[0]['to'] ?? []) === [$email], 'Notification sent only to intended fictional recipient');
    check(str_contains(Mailer::$sent[0]['html'] ?? '', BODY), 'Notification includes submitted message');
}

try {
    foreach (['pdo_sqlite', 'mbstring', 'tokenizer'] as $extension) {
        if (!extension_loaded($extension)) { throw new RuntimeException('Required extension unavailable: ' . $extension . '; no mock/skip fallback'); }
    }
    if ($argc > 2) { throw new RuntimeException('Usage: php tools/test-parent-access.php [source-root]'); }
    $root = realpath($argv[1] ?? dirname(__DIR__));
    if ($root === false || !is_dir($root)) { throw new RuntimeException('Source root does not exist'); }
    echo "ROOT $root\n";
    loadController($root, 'ParentController');
    loadController($root, 'InboxController');
    fixture(); // Harness/schema smoke test: impossible execution is exit 2, not a skip.
} catch (Throwable $e) {
    fwrite(STDERR, 'IMPOSSIBLE: ' . $e->getMessage() . "\nNo successful regression result.\n");
    exit(2);
}

$scenarios = [
    'null link matching email1' => [['link' => null, 'email1' => 'parent@example.invalid'], false, false],
    'null link matching email2' => [['link' => null, 'email2' => 'parent@example.invalid'], false, false],
    'different link matching email1' => [['link' => 3, 'email1' => 'parent@example.invalid'], false, false],
    'different link matching email2' => [['link' => 3, 'email2' => 'parent@example.invalid'], false, false],
    'correct link differing emails' => [[], true, true],
    'null link empty emails' => [['link' => null, 'email' => '', 'email1' => '', 'email2' => ''], false, false],
    'null link SQL NULL emails' => [['link' => null, 'email' => '', 'email1' => null, 'email2' => null], false, false],
    'correct link empty emails' => [['email' => '', 'email1' => '', 'email2' => ''], true, true],
    'inactive linked child' => [['active' => 0, 'email1' => 'parent@example.invalid'], false, false],
    'correct child link another thread owner' => [['owner' => 3], true, false],
];

foreach ($scenarios as $label => [$options, $childAllowed, $threadAllowed]) {
    foreach (['dashboard', 'daily messages', 'inbox children', 'thread list', 'unread', 'inbox view', 'send', 'read', 'reply', 'delete'] as $route) {
        test("$label / $route", function() use ($options, $childAllowed, $threadAllowed, $route): void {
            [$db, $parent, $inbox] = fixture($options);
            $before = snapshot($db);
            switch ($route) {
                case 'dashboard':
                    $parent->index();
                    check(($parent->rendered['view'] ?? '') === 'parent/dashboard', 'Dashboard rendered');
                    check(ids($parent->rendered['data']['children'] ?? []) === ($childAllowed ? [1] : []), 'Only explicitly linked active children visible');
                    check(($parent->rendered['data']['todayCount'] ?? -1) === (int)$childAllowed, 'Today count excludes unauthorized children');
                    break;
                case 'daily messages':
                    $post = ['child_id' => 1, 'from' => '2026-09-01', 'to' => '2026-09-30'];
                    if (!$childAllowed) { denied($db, $parent, 'apiMessages', $post); return; }
                    $r = response($parent, 'apiMessages', $post);
                    check($r->status === 200 && ids($r->data['rows'] ?? []) === [1], 'Linked parent reads daily messages');
                    break;
                case 'inbox children':
                    $r = response($inbox, 'apiChildrenForInbox');
                    check($r->status === 200 && ids($r->data['children'] ?? [], 'child_id') === ($childAllowed ? [1] : []), 'Inbox child visibility');
                    break;
                case 'thread list':
                    $r = response($inbox, 'apiThreadList');
                    check($r->status === 200 && ids($r->data['threads'] ?? []) === ($threadAllowed ? [1] : []), 'Thread owner AND current child link required');
                    if ($threadAllowed) { check((int)($r->data['threads'][0]['unread'] ?? -1) === 1, 'Thread unread count'); }
                    break;
                case 'unread':
                    check($inbox->countUnread(Auth::user()) === (int)$threadAllowed, 'Unread excludes inaccessible threads');
                    break;
                case 'inbox view':
                    $inbox->index();
                    check(($inbox->rendered['view'] ?? '') === 'inbox/index', 'Inbox rendered');
                    check(($inbox->rendered['data']['unread'] ?? -1) === (int)$threadAllowed, 'Inbox view unread count');
                    break;
                case 'send':
                    $post = ['child_id' => 1, 'group_id' => 1, 'subject' => 'Fictional new question', 'body' => BODY];
                    if (!$childAllowed) { denied($db, $inbox, 'apiSendMessage', $post); return; }
                    $r = response($inbox, 'apiSendMessage', $post);
                    success($r);
                    $threadId = (int)($r->data['thread_id'] ?? 0);
                    $s = $db->prepare('SELECT parent_user_id FROM parent_threads WHERE id=?');
                    $s->execute([$threadId]);
                    check((int)$s->fetchColumn() === 1, 'Send uses own thread, never another owner');
                    check($threadId === (($options['owner'] ?? 1) === 1 ? 1 : 2), 'Reuse own thread or create a separate thread');
                    check((int)$db->query('SELECT COUNT(*) FROM parent_thread_messages')->fetchColumn() === 3, 'Exactly one message inserted');
                    checkMail('teacher@example.invalid');
                    return;
                case 'read':
                    if (!$threadAllowed) { denied($db, $inbox, 'apiThreadMessages', ['thread_id' => 1]); return; }
                    $r = response($inbox, 'apiThreadMessages', ['thread_id' => 1]);
                    check($r->status === 200 && ids($r->data['messages'] ?? []) === [1, 2], 'Authorized thread read');
                    check((int)$db->query('SELECT COUNT(*) FROM parent_thread_messages WHERE id=2 AND read_at IS NOT NULL')->fetchColumn() === 1, 'Other sender marked read');
                    check((int)$db->query('SELECT COUNT(*) FROM parent_thread_messages WHERE id=1 AND read_at IS NULL')->fetchColumn() === 1, 'Own message not marked read');
                    check($inbox->countUnread(Auth::user()) === 0, 'Unread cleared by authorized read');
                    return;
                case 'reply':
                    if (!$threadAllowed) { denied($db, $inbox, 'apiReply', ['thread_id' => 1, 'body' => BODY]); return; }
                    success(response($inbox, 'apiReply', ['thread_id' => 1, 'body' => BODY]));
                    check((int)$db->query('SELECT COUNT(*) FROM parent_thread_messages')->fetchColumn() === 3, 'Reply inserted exactly once');
                    checkMail('teacher@example.invalid');
                    return;
                case 'delete':
                    // Message 1 was sent by actor 1, so sender-only checks cannot hide a missing link guard.
                    if (!$threadAllowed) { denied($db, $inbox, 'apiDeleteMessage', ['msg_id' => 1]); return; }
                    success(response($inbox, 'apiDeleteMessage', ['msg_id' => 1]));
                    check(ids($db->query('SELECT id FROM parent_thread_messages')->fetchAll()) === [2], 'Only own message removed');
                    check((int)$db->query('SELECT COUNT(*) FROM parent_threads')->fetchColumn() === 1, 'Nonempty thread retained');
                    return;
            }
            noWrites($db, $before);
            check(Mailer::$sent === [], 'Read-only route sends no mail');
        });
    }
}

foreach (['unlink' => null, 'reassign' => 3] as $label => $newOwner) {
    test("existing controller after $label", function() use ($newOwner): void {
        [$db, $parent, $inbox] = fixture(['email1' => 'parent@example.invalid']);
        check(ids(response($inbox, 'apiThreadList')->data['threads']) === [1], 'Thread initially accessible');
        check($inbox->countUnread(Auth::user()) === 1, 'Initially unread');
        $db->prepare('UPDATE children SET parent_user_id=? WHERE id=1')->execute([$newOwner]);
        $parent->index();
        check(($parent->rendered['data']['children'] ?? null) === [], 'Former parent loses dashboard child immediately');
        check(response($inbox, 'apiThreadList')->data['threads'] === [], 'Old thread hidden immediately');
        check($inbox->countUnread(Auth::user()) === 0, 'Old unread hidden immediately');
        denied($db, $parent, 'apiMessages', ['child_id' => 1]);
        denied($db, $inbox, 'apiSendMessage', ['child_id' => 1, 'group_id' => 1, 'body' => BODY]);
        denied($db, $inbox, 'apiThreadMessages', ['thread_id' => 1]);
        denied($db, $inbox, 'apiReply', ['thread_id' => 1, 'body' => BODY]);
        denied($db, $inbox, 'apiDeleteMessage', ['msg_id' => 1]);
        if ($newOwner !== null) {
            login($db, $newOwner);
            check(ids(response($inbox, 'apiChildrenForInbox')->data['children'], 'child_id') === [1], 'Reassigned parent gets child');
            check(response($inbox, 'apiThreadList')->data['threads'] === [], 'Reassigned parent does not inherit old conversations');
            check($inbox->countUnread(Auth::user()) === 0, 'Reassigned parent does not inherit old unread');
            denied($db, $inbox, 'apiThreadMessages', ['thread_id' => 1]);
            denied($db, $inbox, 'apiReply', ['thread_id' => 1, 'body' => BODY]);
            denied($db, $inbox, 'apiDeleteMessage', ['msg_id' => 1]);
        }
    });
}

$notifications = [
    'active valid parent' => [[], true],
    'former unlinked parent' => [['link' => null], false],
    'former reassigned parent' => [['link' => 3], false],
    'inactive child' => [['active' => 0], false],
    'inactive parent account' => [['userActive' => 0], false],
    'wrong-role teacher account' => [['userRole' => 'teacher'], false],
    'wrong-role admin account' => [['userRole' => 'admin'], false],
    'different thread owner' => [['owner' => 3], false],
    'invalid recipient address' => [['email' => 'not-an-email'], false],
];
foreach ([2 => 'teacher', 4 => 'admin'] as $actor => $role) {
    foreach ($notifications as $label => [$options, $notify]) {
        test("$role reply notification / $label", function() use ($actor, $options, $notify): void {
            [$db, , $inbox] = fixture($options);
            login($db, $actor);
            success(response($inbox, 'apiReply', ['thread_id' => 1, 'body' => BODY]));
            $row = $db->query('SELECT sender_id,body FROM parent_thread_messages ORDER BY id DESC LIMIT 1')->fetch();
            check((int)$row['sender_id'] === $actor && $row['body'] === BODY, 'Staff reply still persisted');
            check((int)$db->query('SELECT COUNT(*) FROM parent_thread_messages')->fetchColumn() === 3, 'Exactly one staff reply');
            if ($notify) { checkMail('parent@example.invalid'); }
            else { check(Mailer::$sent === [], 'No notification to former/inactive/wrong-role/invalid parent'); }
        });
    }
    foreach (['list', 'unread', 'read', 'delete own', 'delete other'] as $route) {
        test("$role unchanged / $route", function() use ($actor, $role, $route): void {
            [$db, , $inbox] = fixture(['link' => null]);
            login($db, $actor);
            switch ($route) {
                case 'list':
                    check(ids(response($inbox, 'apiThreadList')->data['threads']) === [1], 'Staff retains historical thread list');
                    break;
                case 'unread':
                    check($inbox->countUnread(Auth::user()) === ($role === 'admin' ? 2 : 1), 'Staff unread unchanged');
                    break;
                case 'read':
                    $r = response($inbox, 'apiThreadMessages', ['thread_id' => 1]);
                    check($r->status === 200 && ids($r->data['messages'] ?? []) === [1, 2], 'Staff reads historical thread');
                    break;
                case 'delete own':
                    $db->prepare('INSERT INTO parent_thread_messages (id,thread_id,sender_id,body) VALUES (3,1,?,?)')->execute([$actor, BODY]);
                    success(response($inbox, 'apiDeleteMessage', ['msg_id' => 3]));
                    check(ids($db->query('SELECT id FROM parent_thread_messages')->fetchAll()) === [1, 2], 'Staff deletes own message');
                    break;
                case 'delete other':
                    if ($role === 'teacher') { denied($db, $inbox, 'apiDeleteMessage', ['msg_id' => 1]); }
                    else {
                        success(response($inbox, 'apiDeleteMessage', ['msg_id' => 1]));
                        check(ids($db->query('SELECT id FROM parent_thread_messages')->fetchAll()) === [2], 'Admin deletes another sender message');
                    }
                    break;
            }
        });
    }
}

test('unassigned teacher denied', function(): void {
    [$db, , $inbox] = fixture();
    login($db, 5);
    check(response($inbox, 'apiThreadList')->data['threads'] === [], 'Unassigned teacher list empty');
    check($inbox->countUnread(Auth::user()) === 0, 'Unassigned teacher unread empty');
    denied($db, $inbox, 'apiThreadMessages', ['thread_id' => 1]);
    denied($db, $inbox, 'apiReply', ['thread_id' => 1, 'body' => BODY]);
    denied($db, $inbox, 'apiDeleteMessage', ['msg_id' => 1]);
});

test('linked parent cannot delete teacher message', function(): void {
    [$db, , $inbox] = fixture();
    denied($db, $inbox, 'apiDeleteMessage', ['msg_id' => 2]);
});

test('linked parent last-message deletion removes empty thread', function(): void {
    [$db, , $inbox] = fixture();
    $db->exec('DELETE FROM parent_thread_messages WHERE id=2');
    $r = response($inbox, 'apiDeleteMessage', ['msg_id' => 1]);
    success($r);
    check(($r->data['thread_deleted'] ?? false) === true, 'Empty thread deletion response');
    check((int)$db->query('SELECT COUNT(*) FROM parent_threads')->fetchColumn() === 0, 'Empty thread removed');
    check((int)$db->query('SELECT COUNT(*) FROM parent_thread_messages')->fetchColumn() === 0, 'No messages remain');
});

test('mixed allowed and denied children/threads are filtered, not all hidden', function(): void {
    [$db, $parent, $inbox] = fixture();
    $db->exec(<<<'SQL'
INSERT INTO children VALUES (2,'Hidden','Unlinked',NULL,'parent@example.invalid','',1),
    (3,'Hidden','Reassigned',3,'parent@example.invalid','',1),
    (4,'Hidden','Inactive',1,'parent@example.invalid','',0);
INSERT INTO children_groups VALUES (2,1),(3,1),(4,1);
INSERT INTO messages VALUES (2,2,1,'2026-09-25','Hidden'),(3,3,1,'2026-09-25','Hidden'),(4,4,1,'2026-09-25','Hidden');
INSERT INTO parent_threads (id,child_id,parent_user_id,group_id,subject) VALUES
    (2,2,1,1,'Hidden'),(3,3,1,1,'Hidden'),(4,4,1,1,'Hidden'),(5,1,3,1,'Other owner');
INSERT INTO parent_thread_messages (thread_id,sender_id,body) VALUES
    (2,2,'Hidden'),(3,2,'Hidden'),(4,2,'Hidden'),(5,2,'Hidden');
SQL);
    $before = snapshot($db);
    $parent->index();
    check(ids($parent->rendered['data']['children'] ?? []) === [1], 'Mixed dashboard visibility');
    check(($parent->rendered['data']['todayCount'] ?? -1) === 1, 'Mixed dashboard today count');
    check(ids(response($inbox, 'apiChildrenForInbox')->data['children'], 'child_id') === [1], 'Mixed inbox child visibility');
    check(ids(response($inbox, 'apiThreadList')->data['threads']) === [1], 'Mixed thread visibility');
    check($inbox->countUnread(Auth::user()) === 1, 'Mixed unread count');
    noWrites($db, $before);
});

printf("\nRESULT: %d cases, %d passed, %d failed; %d assertions, %d failed assertions.\n",
    $cases, $passed, $cases - $passed, $assertions, $failedAssertions);
echo "UNCOVERED: real Auth/session/CSRF, templates/HTTP routing, MySQL-specific semantics/collations,\n"
    . "concurrent reassignment races/transactions, live production state, real SMTP delivery,\n"
    . "other controllers, group-membership send validation, full staff permission matrix.\n";
exit($passed === $cases ? 0 : 1);