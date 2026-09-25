<?php
/**
 * Shared SMTP mailer used by InboxController (and optionally other controllers).
 * Mirrors the logic in MessagesController::sendEmail().
 */
class Mailer {
    private string $lastError = '';

    public function getLastError(): string {
        return $this->lastError;
    }

    public function send(array $to, string $subject, string $htmlBody): bool {
        $this->lastError = '';

        $host     = trim((string)(defined('SMTP_HOST')      ? SMTP_HOST      : 'localhost'));
        $port     = defined('SMTP_PORT')   ? (int)SMTP_PORT : 587;
        $user     = trim((string)(defined('SMTP_USER')      ? SMTP_USER      : ''));
        $pass     = (string)(defined('SMTP_PASS')           ? SMTP_PASS      : '');
        $secure   = strtolower((string)(defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'));
        $from     = trim((string)(defined('SMTP_FROM')      ? SMTP_FROM      : $user));
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME_GR') ? APP_NAME_GR : 'School Email');

        // Override sender name with school name from parameters if available
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare('SELECT param_value FROM parameters WHERE param_key = ? LIMIT 1');
            $stmt->execute(['school_name']);
            $school = trim((string)$stmt->fetchColumn());
            if ($school !== '') $fromName = $school;
        } catch (Throwable $e) {}

        $heloHost = $host;
        if (strpos($from, '@') !== false) {
            $parts  = explode('@', $from);
            $domain = trim((string)end($parts));
            if ($domain !== '') $heloHost = $domain;
        }

        if ($host === '' || $from === '') {
            $this->lastError = 'Λείπουν βασικές SMTP ρυθμίσεις (host/from).';
            return false;
        }

        if ($user !== '' && $pass === '') {
            $this->lastError = 'Το SMTP_PASS είναι κενό.';
            return false;
        }

        if (!in_array($secure, ['tls', 'ssl'], true)) {
            $this->lastError = 'Μη υποστηριζόμενη τιμή SMTP_SECURE.';
            return false;
        }

        $transportHost = $secure === 'ssl' ? 'ssl://' . $host : $host;
        $errno  = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $transportHost . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $this->socketContext($host)
        );
        if (!$socket) {
            $this->lastError = 'SMTP σύνδεση απέτυχε: ' . $errstr . ' (' . $errno . ')';
            return false;
        }

        stream_set_timeout($socket, 20);

        if (!$this->expect($socket, [220])) { fclose($socket); return false; }
        if (!$this->cmd($socket, 'EHLO ' . $heloHost, [250])) { fclose($socket); return false; }

        if ($secure === 'tls') {
            if (!$this->cmd($socket, 'STARTTLS', [220])) { fclose($socket); return false; }
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = 'Αποτυχία TLS handshake.';
                fclose($socket);
                return false;
            }
            if (!$this->cmd($socket, 'EHLO ' . $heloHost, [250])) { fclose($socket); return false; }
        }

        if ($user !== '' && !$this->auth($socket, $user, $pass)) {
            fclose($socket);
            return false;
        }

        if (!$this->cmd($socket, 'MAIL FROM:<' . $this->sanitize($from) . '>', [250])) {
            fclose($socket);
            return false;
        }

        $recipients = array_values(array_unique(array_filter(array_map([$this, 'sanitize'], $to))));
        if (empty($recipients)) {
            $this->lastError = 'Δεν υπάρχουν έγκυροι παραλήπτες.';
            fclose($socket);
            return false;
        }

        foreach ($recipients as $addr) {
            if (!$this->cmd($socket, 'RCPT TO:<' . $addr . '>', [250, 251])) {
                fclose($socket);
                return false;
            }
        }

        if (!$this->cmd($socket, 'DATA', [354])) { fclose($socket); return false; }

        $encSubject  = '=?UTF-8?B?' . base64_encode($subject)  . '?=';
        $encFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $boundary    = 'b1_' . bin2hex(random_bytes(12));
        $textBody    = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
        if ($textBody === '') $textBody = ' ';

        $headers = [
            'Date: '       . date('r'),
            'From: '       . $encFromName . ' <' . $from . '>',
            'To: '         . implode(', ', $recipients),
            'Reply-To: <'  . $from . '>',
            'Message-ID: <'. bin2hex(random_bytes(12)) . '@' . $host . '>',
            'Subject: '    . $encSubject,
            'MIME-Version: 1.0',
            'X-Mailer: KinderLink/1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $mime  = '--' . $boundary . "\r\n";
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
        $mime .= quoted_printable_encode($textBody) . "\r\n\r\n";
        $mime .= '--' . $boundary . "\r\n";
        $mime .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
        $mime .= quoted_printable_encode($htmlBody) . "\r\n\r\n";
        $mime .= '--' . $boundary . "--\r\n";

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $mime;
        $payload = preg_replace('/(?m)^\./', '..', $payload);
        fwrite($socket, $payload . "\r\n.\r\n");

        if (!$this->expect($socket, [250])) { fclose($socket); return false; }

        $this->cmd($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    }

    private function cmd($socket, string $command, array $okCodes): bool {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $okCodes);
    }

    private function expect($socket, array $okCodes): bool {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            $this->lastError = trim($response) !== '' ? trim($response) : 'Άγνωστη απάντηση SMTP.';
            return false;
        }
        return true;
    }

    private function auth($socket, string $user, string $pass): bool {
        $prevError = '';

        if ($this->cmd($socket, 'AUTH LOGIN', [334])
            && $this->cmd($socket, base64_encode($user), [334])
            && $this->cmd($socket, base64_encode($pass), [235])) {
            return true;
        }
        $prevError = $this->lastError;

        $plainToken = base64_encode("\0" . $user . "\0" . $pass);
        if ($this->cmd($socket, 'AUTH PLAIN ' . $plainToken, [235])) {
            return true;
        }

        if ($prevError !== '' && $this->lastError !== '' && $this->lastError !== $prevError) {
            $this->lastError .= ' | AUTH LOGIN: ' . $prevError;
        } elseif ($this->lastError === '') {
            $this->lastError = $prevError !== '' ? $prevError : 'Αποτυχία SMTP authentication.';
        }

        return false;
    }

    private function sanitize(string $email): string {
        return trim(str_replace(["\r", "\n", "<", ">"], '', $email));
    }

    private function socketContext(string $host) {
        $ssl = [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'allow_self_signed'=> false,
            'peer_name'        => $host,
            'SNI_enabled'      => true,
            'crypto_method'    => STREAM_CRYPTO_METHOD_TLS_CLIENT,
        ];
        $caFile = trim((string)app_env('SMTP_CA_FILE', ''));
        if ($caFile !== '' && is_readable($caFile)) $ssl['cafile'] = $caFile;
        $caPath = trim((string)app_env('SMTP_CA_PATH', ''));
        if ($caPath !== '' && is_dir($caPath)) $ssl['capath'] = $caPath;
        return stream_context_create(['ssl' => $ssl]);
    }
}
