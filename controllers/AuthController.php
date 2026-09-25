<?php
class AuthController extends Controller {
    private string $lastMailError = '';
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_SECONDS = 900; // 15 minutes
    private const FORGOT_MAX_ATTEMPTS_PER_DAY = 5;
    private const TEMP_PASSWORD_EXPIRY_HOURS = 24;
    private ?bool $hasLoginAttemptsTable = null;

    public function loginPage(): void {
        if (Auth::check()) {
            $this->redirectByRole();
        }
        $this->render('auth/login', [], 'public');
    }

    public function forgotPasswordPage(): void {
        if (Auth::check()) {
            $this->redirectByRole();
        }

        $this->render('auth/forgot-password', [
            'pageTitle' => 'Ανάκτηση Κωδικού',
        ], 'public');
    }

    public function forgotPassword(): void {
        if (Auth::check()) {
            $this->redirectByRole();
        }

        $this->verifyCsrf();

        // Rate limit: max 5 requests per day per IP.
        if ($this->isForgotRateLimited('fp')) {
            $this->render('auth/forgot-password', [
                'pageTitle' => 'Ανάκτηση Κωδικού',
                'error' => 'Πολλές απόπειρες. Δοκιμάστε ξανά αύριο.',
            ], 'public');
            return;
        }
        $this->registerForgotAttempt('fp');

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($username === '' || $email === '') {
            $this->render('auth/forgot-password', [
                'pageTitle' => 'Ανάκτηση Κωδικού',
                'error' => 'Παρακαλώ συμπληρώστε όνομα χρήστη και email.',
                'form' => compact('username', 'email'),
            ], 'public');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('auth/forgot-password', [
                'pageTitle' => 'Ανάκτηση Κωδικού',
                'error' => 'Μη έγκυρη διεύθυνση email.',
                'form' => compact('username', 'email'),
            ], 'public');
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT id, username, name, email, active FROM users WHERE username = ? AND email = ? LIMIT 1'
        );
        $stmt->execute([$username, $email]);
        $user = $stmt->fetch();

        if (!$user || !(int)$user['active']) {
            $this->render('auth/forgot-password', [
                'pageTitle' => 'Ανάκτηση Κωδικού',
                'success' => 'Αν τα στοιχεία σας είναι σωστά, έχει σταλεί email με οδηγίες ανάκτησης.',
            ], 'public');
            return;
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $subject = 'Προσωρινός κωδικός πρόσβασης';
        $body = '<p>Γεια σας ' . $this->e($user['name']) . ',</p>'
            . '<p>Ζητήθηκε επαναφορά κωδικού για τον λογαριασμό σας στο ' . $this->e(APP_NAME_GR) . '.</p>'
            . '<p><strong>Προσωρινός κωδικός:</strong> ' . $this->e($temporaryPassword) . '</p>'
            . '<p>Συνδεθείτε και αλλάξτε άμεσα τον κωδικό σας από τα προσωπικά στοιχεία.</p>';

        try {
            // Check if temp_password_expires column exists (requires security-migration-2026-08-06.sql).
            $hasExpiryCol = false;
            try {
                $colCheck = $this->db->query("SHOW COLUMNS FROM users LIKE 'temp_password_expires'");
                $hasExpiryCol = $colCheck && $colCheck->rowCount() > 0;
            } catch (Throwable $e) {}

            $this->db->beginTransaction();

            if ($hasExpiryCol) {
                $this->db->prepare('UPDATE users SET password = ?, temp_password_expires = DATE_ADD(NOW(), INTERVAL ' . self::TEMP_PASSWORD_EXPIRY_HOURS . ' HOUR) WHERE id = ?')
                    ->execute([Auth::hashPassword($temporaryPassword), $user['id']]);
            } else {
                $this->db->prepare('UPDATE users SET password = ? WHERE id = ?')
                    ->execute([Auth::hashPassword($temporaryPassword), $user['id']]);
            }

            if (!$this->sendEmail([$user['email']], $subject, $body)) {
                $this->db->rollBack();
                $this->render('auth/forgot-password', [
                    'pageTitle' => 'Ανάκτηση Κωδικού',
                    'error' => $this->lastMailError !== '' ? $this->lastMailError : 'Αποτυχία αποστολής email.',
                    'form' => compact('username', 'email'),
                ], 'public');
                return;
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->render('auth/forgot-password', [
                'pageTitle' => 'Ανάκτηση Κωδικού',
                'error' => 'Δεν ήταν δυνατή η ολοκλήρωση της επαναφοράς κωδικού.',
                'form' => compact('username', 'email'),
            ], 'public');
            return;
        }

        $this->render('auth/forgot-password', [
            'pageTitle' => 'Ανάκτηση Κωδικού',
            'success' => 'Στάλθηκε προσωρινός κωδικός στο email σας.',
        ], 'public');
    }

    public function forgotUsernamePage(): void {
        if (Auth::check()) {
            $this->redirectByRole();
        }

        $this->render('auth/forgot-username', [
            'pageTitle' => 'Υπενθύμιση Ονόματος Χρήστη',
        ], 'public');
    }

    public function forgotUsername(): void {
        if (Auth::check()) {
            $this->redirectByRole();
        }

        $this->verifyCsrf();

        // Rate limit: max 5 requests per day per IP.
        if ($this->isForgotRateLimited('fu')) {
            $this->render('auth/forgot-username', [
                'pageTitle' => 'Υπενθύμιση Ονόματος Χρήστη',
                'error' => 'Πολλές απόπειρες. Δοκιμάστε ξανά αύριο.',
            ], 'public');
            return;
        }
        $this->registerForgotAttempt('fu');

        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            $this->render('auth/forgot-username', [
                'pageTitle' => 'Υπενθύμιση Ονόματος Χρήστη',
                'error' => 'Παρακαλώ συμπληρώστε το email σας.',
                'form' => compact('email'),
            ], 'public');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('auth/forgot-username', [
                'pageTitle' => 'Υπενθύμιση Ονόματος Χρήστη',
                'error' => 'Μη έγκυρη διεύθυνση email.',
                'form' => compact('email'),
            ], 'public');
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT username, name, email, active FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !(int)$user['active']) {
            $this->render('auth/forgot-username', [
                'pageTitle' => 'Υπενθύμιση Ονόματος Χρήστη',
                'success' => 'Αν το email σας υπάρχει στο σύστημα, έχει σταλεί υπενθύμιση.',
            ], 'public');
            return;
        }

        $subject = 'Υπενθύμιση ονόματος χρήστη';
        $body = '<p>Γεια σας ' . $this->e($user['name']) . ',</p>'
            . '<p>Το όνομα χρήστη σας για το ' . $this->e(APP_NAME_GR) . ' είναι:</p>'
            . '<p><strong>' . $this->e($user['username']) . '</strong></p>';

        if (!$this->sendEmail([$user['email']], $subject, $body)) {
            $this->render('auth/forgot-username', [
                'pageTitle' => 'Υπενθύμιση Ονόματος Χρήστη',
                'error' => $this->lastMailError !== '' ? $this->lastMailError : 'Αποτυχία αποστολής email.',
                'form' => compact('email'),
            ], 'public');
            return;
        }

        $this->render('auth/forgot-username', [
            'pageTitle' => 'Υπενθύμιση Ονόματος Χρήστη',
            'success' => 'Στάλθηκε υπενθύμιση ονόματος χρήστη στο email σας.',
        ], 'public');
    }

    public function login(): void {
        if (Auth::check()) {
            $this->redirectByRole();
        }

        $this->verifyCsrf();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $rateKey = $this->loginRateKey($username);
        $state = $this->getLoginRateState($rateKey);
        if (($state['locked_until'] ?? 0) > time()) {
            $minutes = (int)ceil((($state['locked_until'] ?? 0) - time()) / 60);
            $this->render('auth/login', [
                'error' => 'Πολλές αποτυχημένες προσπάθειες. Δοκιμάστε ξανά σε περίπου ' . max(1, $minutes) . ' λεπτό/ά.',
            ], 'public');
            return;
        }

        if ($username === '' || $password === '') {
            $this->render('auth/login', ['error' => 'Παρακαλώ συμπληρώστε όνομα χρήστη και κωδικό.'], 'public');
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT id, username, password, name, email, role, active, temp_password_expires FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !Auth::verifyPassword($password, $user['password'])) {
            $this->registerLoginFailure($rateKey);
            $this->render('auth/login', ['error' => 'Λάθος όνομα χρήστη ή κωδικός.'], 'public');
            return;
        }

        if (!$user['active']) {
            $this->render('auth/login', ['error' => 'Ο λογαριασμός σας είναι ανενεργός.'], 'public');
            return;
        }

        // Check if temp password has expired.
        if (!empty($user['temp_password_expires'])) {
            $expires = strtotime((string)$user['temp_password_expires']);
            if ($expires && $expires < time()) {
                $this->render('auth/login', [
                    'error' => 'Ο προσωρινός κωδικός έχει λήξει. Παρακαλώ ζητήστε νέο κωδικό ανάκτησης.',
                ], 'public');
                return;
            }
        }

        $this->clearLoginRateState($rateKey);
        // Clear temp password expiry on first successful login (graceful if column missing).
        try {
            $this->db->prepare('UPDATE users SET temp_password_expires = NULL WHERE id = ?')->execute([$user['id']]);
        } catch (Throwable $e) {}
        Auth::login($user);
        $this->redirectByRole();
    }

    public function logout(): void {
        $this->verifyCsrf();
        Auth::logout();
        $this->redirect('/');
    }

    /**
     * Session ping — keep-alive check
     * Returns JSON: {alive: true/false}
     */
    public function ping(): void {
        if (!Auth::check()) {
            http_response_code(401);
            $this->json(['alive' => false, 'message' => 'Session expired'], 401);
            return;
        }
        $this->json(['alive' => true, 'user_id' => Auth::userId()]);
    }

    private function redirectByRole(): void {
        if (Auth::isParent()) {
            $this->redirect('/parent/dashboard');
        }
        $this->redirect('/dashboard');
    }

    private function generateTemporaryPassword(): string {
        return strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    }

    private function forgotRateKey(string $type): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return 'forgot_' . $type . '_' . md5($ip) . '_' . date('Y-m-d');
    }

    private function isForgotRateLimited(string $type): bool {
        $key = $this->forgotRateKey($type);
        $attempts = (int)($_SESSION[$key] ?? 0);
        return $attempts >= self::FORGOT_MAX_ATTEMPTS_PER_DAY;
    }

    private function registerForgotAttempt(string $type): void {
        $key = $this->forgotRateKey($type);
        $_SESSION[$key] = (int)($_SESSION[$key] ?? 0) + 1;
    }

    private function sendEmail(array $to, string $subject, string $body): bool {
        $this->lastMailError = '';

        $host = trim((string)(defined('SMTP_HOST') ? SMTP_HOST : 'localhost'));
        $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $user = trim((string)(defined('SMTP_USER') ? SMTP_USER : ''));
        $pass = (string)(defined('SMTP_PASS') ? SMTP_PASS : '');
        $secure = strtolower((string)(defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'));
        $from = trim((string)(defined('SMTP_FROM') ? SMTP_FROM : $user));
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME_GR;
        $heloHost = $host;
        if (strpos($from, '@') !== false) {
            $parts = explode('@', $from);
            $domain = trim((string)end($parts));
            if ($domain !== '') {
                $heloHost = $domain;
            }
        }

        if ($host === '' || $from === '') {
            $this->lastMailError = 'Λείπουν βασικές SMTP ρυθμίσεις (host/from).';
            return false;
        }

        if ($user !== '' && $pass === '') {
            $this->lastMailError = 'Το SMTP_PASS είναι κενό. Ορίστε σωστό κωδικό SMTP στο environment του server.';
            return false;
        }

        if (!in_array($secure, ['tls', 'ssl'], true)) {
            $this->lastMailError = 'Μη υποστηριζόμενη τιμή SMTP_SECURE. Επιτρεπτές τιμές: tls ή ssl.';
            return false;
        }

        $transportHost = $secure === 'ssl' ? 'ssl://' . $host : $host;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $transportHost . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $this->smtpSocketContext($host)
        );
        if (!$socket) {
            $this->lastMailError = 'SMTP σύνδεση απέτυχε: ' . $errstr . ' (' . $errno . ')';
            return false;
        }

        stream_set_timeout($socket, 20);

        if (!$this->smtpExpect($socket, [220])) {
            fclose($socket);
            return false;
        }

        if (!$this->smtpCommand($socket, 'EHLO ' . $heloHost, [250])) {
            fclose($socket);
            return false;
        }

        if ($secure === 'tls') {
            if (!$this->smtpCommand($socket, 'STARTTLS', [220])) {
                fclose($socket);
                return false;
            }
            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$cryptoOk) {
                $this->lastMailError = 'Αποτυχία TLS handshake με SMTP server.';
                fclose($socket);
                return false;
            }
            if (!$this->smtpCommand($socket, 'EHLO ' . $heloHost, [250])) {
                fclose($socket);
                return false;
            }
        }

        if ($user !== '') {
            if (!$this->smtpAuthenticate($socket, $user, $pass)) {
                fclose($socket);
                return false;
            }
        }

        if (!$this->smtpCommand($socket, 'MAIL FROM:<' . $this->sanitizeEmail($from) . '>', [250])) {
            fclose($socket);
            return false;
        }

        $recipients = array_values(array_unique(array_filter(array_map([$this, 'sanitizeEmail'], $to))));
        if (empty($recipients)) {
            $this->lastMailError = 'Δεν υπάρχουν έγκυρες διευθύνσεις παραληπτών.';
            fclose($socket);
            return false;
        }

        foreach ($recipients as $addr) {
            if (!$this->smtpCommand($socket, 'RCPT TO:<' . $addr . '>', [250, 251])) {
                fclose($socket);
                return false;
            }
        }

        if (!$this->smtpCommand($socket, 'DATA', [354])) {
            fclose($socket);
            return false;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $boundary = 'b1_' . bin2hex(random_bytes(12));
        $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)));
        if ($textBody === '') {
            $textBody = ' ';
        }
        $qpTextBody = quoted_printable_encode($textBody);
        $qpHtmlBody = quoted_printable_encode($body);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $encodedFromName . ' <' . $from . '>',
            'To: ' . implode(', ', $recipients),
            'Reply-To: <' . $from . '>',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'X-Mailer: KinderLink/1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $mimeBody = '';
        $mimeBody .= '--' . $boundary . "\r\n";
        $mimeBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mimeBody .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $mimeBody .= $qpTextBody . "\r\n\r\n";
        $mimeBody .= '--' . $boundary . "\r\n";
        $mimeBody .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mimeBody .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $mimeBody .= $qpHtmlBody . "\r\n\r\n";
        $mimeBody .= '--' . $boundary . "--\r\n";

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $mimeBody;
        $payload = preg_replace('/(?m)^\./', '..', $payload);
        fwrite($socket, $payload . "\r\n.\r\n");

        if (!$this->smtpExpect($socket, [250])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    }

    private function smtpCommand($socket, string $command, array $okCodes): bool {
        fwrite($socket, $command . "\r\n");
        return $this->smtpExpect($socket, $okCodes);
    }

    private function smtpAuthenticate($socket, string $user, string $pass): bool {
        $loginError = '';

        if ($this->smtpCommand($socket, 'AUTH LOGIN', [334])
            && $this->smtpCommand($socket, base64_encode($user), [334])
            && $this->smtpCommand($socket, base64_encode($pass), [235])) {
            return true;
        }

        $loginError = $this->lastMailError;

        $plainToken = base64_encode("\0" . $user . "\0" . $pass);
        if ($this->smtpCommand($socket, 'AUTH PLAIN ' . $plainToken, [235])) {
            return true;
        }

        if ($loginError !== '' && $this->lastMailError !== '' && $this->lastMailError !== $loginError) {
            $this->lastMailError .= ' | AUTH LOGIN: ' . $loginError;
        } elseif ($this->lastMailError === '') {
            $this->lastMailError = $loginError !== '' ? $loginError : 'Αποτυχία SMTP authentication.';
        }

        return false;
    }

    private function smtpExpect($socket, array $okCodes): bool {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            $this->lastMailError = trim($response) !== '' ? trim($response) : 'Άγνωστη απάντηση SMTP server.';
            return false;
        }
        return true;
    }

    private function sanitizeEmail(string $email): string {
        return trim(str_replace(["\r", "\n", "<", ">"], '', $email));
    }

    private function smtpSocketContext(string $host) {
        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $host,
            'SNI_enabled' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
        ];

        $caFile = trim((string)app_env('SMTP_CA_FILE', ''));
        if ($caFile !== '' && is_readable($caFile)) {
            $sslOptions['cafile'] = $caFile;
        }

        $caPath = trim((string)app_env('SMTP_CA_PATH', ''));
        if ($caPath !== '' && is_dir($caPath)) {
            $sslOptions['capath'] = $caPath;
        }

        return stream_context_create(['ssl' => $sslOptions]);
    }

    private function loginRateKey(string $username): string {
        $u = strtolower(trim($username));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return sha1($u . '|' . $ip);
    }

    private function getLoginRateState(string $rateKey): array {
        if ($this->loginAttemptsTableExists()) {
            try {
                $stmt = $this->db->prepare(
                    'SELECT fail_count, UNIX_TIMESTAMP(locked_until) AS locked_until_ts
                     FROM auth_login_attempts
                     WHERE key_hash = ?
                     LIMIT 1'
                );
                $stmt->execute([$rateKey]);
                $row = $stmt->fetch();
                if (!$row) {
                    return ['fails' => 0, 'locked_until' => 0];
                }

                return [
                    'fails' => (int)($row['fail_count'] ?? 0),
                    'locked_until' => (int)($row['locked_until_ts'] ?? 0),
                ];
            } catch (Throwable $e) {
                $this->hasLoginAttemptsTable = false;
            }
        }

        if (!isset($_SESSION['login_rate_limits']) || !is_array($_SESSION['login_rate_limits'])) {
            $_SESSION['login_rate_limits'] = [];
        }

        $state = $_SESSION['login_rate_limits'][$rateKey] ?? ['fails' => 0, 'locked_until' => 0];
        if (($state['locked_until'] ?? 0) <= time()) {
            $state['locked_until'] = 0;
            $state['fails'] = (int)($state['fails'] ?? 0);
        }

        $_SESSION['login_rate_limits'][$rateKey] = $state;
        return $state;
    }

    private function registerLoginFailure(string $rateKey): void {
        if ($this->loginAttemptsTableExists()) {
            $username = strtolower(trim((string)($_POST['username'] ?? '')));
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');

            try {
                $this->db->prepare(
                    'INSERT INTO auth_login_attempts (key_hash, ip_address, username_norm, fail_count, first_fail_at, last_fail_at, locked_until)
                     VALUES (?, ?, ?, 1, NOW(), NOW(), NULL)
                     ON DUPLICATE KEY UPDATE
                        fail_count = CASE WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN fail_count ELSE fail_count + 1 END,
                        last_fail_at = NOW()'
                )->execute([$rateKey, substr($ip, 0, 45), mb_substr($username, 0, 100, 'UTF-8')]);

                $lockSeconds = (int)self::LOGIN_LOCKOUT_SECONDS;
                $maxAttempts = (int)self::LOGIN_MAX_ATTEMPTS;
                $this->db->prepare(
                    'UPDATE auth_login_attempts
                     SET locked_until = DATE_ADD(NOW(), INTERVAL ' . $lockSeconds . ' SECOND), fail_count = 0
                     WHERE key_hash = ?
                       AND (locked_until IS NULL OR locked_until <= NOW())
                       AND fail_count >= ' . $maxAttempts
                )->execute([$rateKey]);
                return;
            } catch (Throwable $e) {
                $this->hasLoginAttemptsTable = false;
            }
        }

        $state = $this->getLoginRateState($rateKey);
        $state['fails'] = (int)($state['fails'] ?? 0) + 1;

        if ($state['fails'] >= self::LOGIN_MAX_ATTEMPTS) {
            $state['locked_until'] = time() + self::LOGIN_LOCKOUT_SECONDS;
            $state['fails'] = 0;
        }

        $_SESSION['login_rate_limits'][$rateKey] = $state;
    }

    private function clearLoginRateState(string $rateKey): void {
        if ($this->loginAttemptsTableExists()) {
            try {
                $stmt = $this->db->prepare('DELETE FROM auth_login_attempts WHERE key_hash = ?');
                $stmt->execute([$rateKey]);
                return;
            } catch (Throwable $e) {
                $this->hasLoginAttemptsTable = false;
            }
        }

        if (isset($_SESSION['login_rate_limits'][$rateKey])) {
            unset($_SESSION['login_rate_limits'][$rateKey]);
        }
    }

    private function loginAttemptsTableExists(): bool {
        if ($this->hasLoginAttemptsTable !== null) {
            return $this->hasLoginAttemptsTable;
        }

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'auth_login_attempts'");
            $this->hasLoginAttemptsTable = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->hasLoginAttemptsTable = false;
        }

        return $this->hasLoginAttemptsTable;
    }
}
