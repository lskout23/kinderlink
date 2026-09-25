<?php
/** First administrator only. No application bootstrap, env files, SQL imports or mail. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
require_once __DIR__ . '/../core/Auth.php';

function installEnvironment(string $key, ?string $default = null): string {
    $value = getenv('KINDERLINK_INSTALL_' . $key);
    if ($value === false) {
        if ($default !== null) return $default;
        throw new InvalidArgumentException('Missing KINDERLINK_INSTALL_' . $key . ' environment variable.');
    }
    return $value;
}

function provisionFirstAdmin(string $username, string $email): string {
    $host = installEnvironment('DB_HOST');
    $port = installEnvironment('DB_PORT', '3306');
    $database = installEnvironment('DB_NAME');
    $user = installEnvironment('DB_USER');
    // Windows proc_open drops empty environment values. Permit an explicit local-only
    // development opt-in; remote installs still require a supplied password variable.
    $allowEmpty = getenv('KINDERLINK_INSTALL_ALLOW_EMPTY_LOCAL_PASSWORD') === '1'
        && in_array($host, ['127.0.0.1', '::1'], true);
    $dbPassword = installEnvironment('DB_PASS', $allowEmpty ? '' : null);
    if (!preg_match('/\A[A-Za-z0-9_.:-]+\z/', $host)
        || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535
        || !preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $database) || $user === '') {
        throw new InvalidArgumentException('Invalid dedicated installation database settings.');
    }
    if (in_array(strtolower($database), ['mysql', 'information_schema', 'performance_schema', 'sys'], true)) {
        throw new InvalidArgumentException('System databases are not installation targets.');
    }

    $db = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $user, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
    // Server-wide, database-scoped lock, independent of username/host spelling.
    // Lowercasing also serializes invocations on case-insensitive MySQL servers.
    $lockName = 'kinderlink-first-admin-' . substr(hash('sha256', strtolower($database)), 0, 40);
    $locked = false;
    try {
        $lock = $db->prepare('SELECT GET_LOCK(?, 15)');
        $lock->execute([$lockName]);
        $locked = (int)$lock->fetchColumn() === 1;
        if (!$locked) throw new RuntimeException('Installation is busy; no administrator was created.');

        $engine = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchColumn();
        if (strtolower((string)$engine) !== 'innodb') {
            throw new RuntimeException('Import the complete fresh-install schema into an empty database first.');
        }
        $db->beginTransaction();
        // Do not filter on active: an inactive administrator must also block provisioning.
        if ($db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn() !== false) {
            throw new RuntimeException('Refused: an administrator already exists (active or inactive).');
        }
        // This is not an account recovery tool or an installer for an existing school.
        foreach (['users', 'children', 'groups', 'children_groups', 'teacher_groups', 'child_attendance',
            'financial_activities', 'messages', 'message_photos', 'free_emails', 'income', 'expenses',
            'auth_login_attempts', 'parent_threads', 'parent_thread_messages'] as $table) {
            if ((int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() !== 0) {
                throw new RuntimeException('Refused: only a fresh installation without school records is supported.');
            }
        }

        do {
            $password = base64_encode(random_bytes(24)); // 192 random bits, 32 chars, below bcrypt's limit.
        } while (Auth::validatePasswordStrength($password) !== '');
        $hash = Auth::hashPassword($password);
        $insert = $db->prepare("INSERT INTO users (username, password, name, email, role, active, temp_password_expires)
            VALUES (?, ?, ?, ?, 'admin', 1, NULL)");
        $insert->execute([$username, $hash, 'Διαχειριστής', $email]);
        $db->commit();
        return $password; // Returned only after a successful commit; finally runs before display.
    } finally {
        try {
            if ($db->inTransaction()) $db->rollBack();
        } finally {
            if ($locked) {
                try {
                    $release = $db->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                    $release->fetchColumn();
                } catch (Throwable $ignored) {
                    // The nonpersistent connection closes when this function unwinds,
                    // releasing a server-side lock even if explicit release failed.
                }
            }
            unset($release, $insert, $lock);
            $db = null;
        }
    }
}

try {
    if ($argc === 2 && $argv[1] === '--help') {
        fwrite(STDOUT, "Usage: php tools/create-admin.php --confirm-new-install --username=YOUR_USERNAME --email=YOUR_EMAIL\n"
            . "Requires KINDERLINK_INSTALL_DB_HOST, DB_NAME, DB_USER, DB_PASS (each with KINDERLINK_INSTALL_ prefix); DB_PORT is optional.\n"
            . "No env files are loaded. Passwords are never accepted as arguments. Keep success output private.\n");
        exit(0);
    }
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--confirm-new-install' && !isset($options['confirm'])) {
            $options['confirm'] = true;
        } elseif (preg_match('/\A--(username|email)=(.+)\z/D', $argument, $match) && !isset($options[$match[1]])) {
            $options[$match[1]] = $match[2];
        } else {
            throw new InvalidArgumentException('Unknown, duplicate or invalid argument. Use --help.');
        }
    }
    $username = $options['username'] ?? '';
    $email = $options['email'] ?? '';
    if (!isset($options['confirm']) || !Auth::isValidUsername($username)
        || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Explicit --confirm-new-install, valid --username and --email are required. Use --help.');
    }
    $password = provisionFirstAdmin($username, $email);
    fwrite(STDOUT, json_encode([
        'status' => 'created', 'username' => $username, 'email' => $email, 'initial_password' => $password,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    unset($password);
    exit(0);
} catch (Throwable $e) {
    // Never emit driver diagnostics, connection strings, passwords or a stack trace.
    $safe = ($e instanceof InvalidArgumentException || get_class($e) === RuntimeException::class)
        ? $e->getMessage() : 'Provisioning failed; verify the dedicated database settings and fresh schema privately.';
    fwrite(STDERR, $safe . PHP_EOL);
    exit(1);
}