<?php
class ParametersController extends Controller {
    private const PHOTO_HIDDEN_GRACE_DAYS_DEFAULT = 15;
    private const PHOTO_HIDDEN_GRACE_DAYS_MIN = 1;
    private const PHOTO_HIDDEN_GRACE_DAYS_MAX = 60;

    private const PHOTO_RETENTION_MONTHS_DEFAULT = 6;
    private const PHOTO_RETENTION_MONTHS_MIN = 1;
    private const PHOTO_RETENTION_MONTHS_MAX = 24;

    private const PHOTO_RETENTION_DAYS_DEFAULT = 180;
    private const PHOTO_RETENTION_DAYS_MIN = 1;
    private const PHOTO_RETENTION_DAYS_MAX = 730;

    public function index(): void {
        Auth::requireRole('admin');

        $params = $this->db->query('SELECT param_key, param_value FROM parameters')->fetchAll();
        $p = [];
        foreach ($params as $row) $p[$row['param_key']] = $row['param_value'];

        $smtpPass = app_env('SMTP_PASS', '');
        $smtpSource = function_exists('app_env_source') ? app_env_source('SMTP_PASS') : 'unknown';
        $smtpHealth = [
            'configured' => $smtpPass !== '',
            'source' => $smtpSource,
        ];

        $photoHiddenGraceDays = $this->normalizeParameterInt(
            $p['photo_hidden_grace_days'] ?? null,
            self::PHOTO_HIDDEN_GRACE_DAYS_DEFAULT,
            self::PHOTO_HIDDEN_GRACE_DAYS_MIN,
            self::PHOTO_HIDDEN_GRACE_DAYS_MAX
        );
        $photoRetentionDays = $this->normalizeParameterInt(
            $p['photo_retention_days'] ?? null,
            self::PHOTO_RETENTION_DAYS_DEFAULT,
            self::PHOTO_RETENTION_DAYS_MIN,
            self::PHOTO_RETENTION_DAYS_MAX
        );

        try {
            $attendanceReady = (new Attendance($this->db))->isReady();
        } catch (Throwable $e) {
            $attendanceReady = false;
        }
        $this->render('parameters/index', [
            'attendanceReady' => $attendanceReady,
            'pageTitle' => 'Παράμετροι',
            'params'    => $p,
            'smtpHealth' => $smtpHealth,
            'photoHiddenGraceDays' => $photoHiddenGraceDays,
            'photoRetentionDays' => $photoRetentionDays,
        ]);
    }

    public function apiMigrateAttendance(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();
        if (($_POST['backup_confirmed'] ?? '') !== '1') {
            $this->json(['error' => 'Επιβεβαιώστε πρώτα ότι υπάρχει πρόσφατο backup της βάσης.'], 422);
            return;
        }
        try {
            (new Attendance($this->db))->migrate();
            $this->json(['success' => true, 'attendance_ready' => true]);
        } catch (Throwable $e) {
            error_log('[attendance migration] ' . get_class($e) . ' code=' . $e->getCode());
            $this->json(['error' => 'Η αναβάθμιση δεν επιβεβαιώθηκε. Ελέγξτε τα δικαιώματα CREATE/REFERENCES, τα αρχεία αναβάθμισης και τυχόν ασύμβατο πίνακα με τον διαχειριστή βάσης. Δεν έγινε διαγραφή δεδομένων.'], 503);
        }
    }

    public function save(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $emailMode  = in_array($_POST['email_mode'] ?? '', ['real', 'virtual']) ? $_POST['email_mode'] : 'virtual';
        $schoolName = trim($_POST['school_name'] ?? '');
        $photoHiddenGraceDays = $this->normalizeParameterInt(
            $_POST['photo_hidden_grace_days'] ?? null,
            self::PHOTO_HIDDEN_GRACE_DAYS_DEFAULT,
            self::PHOTO_HIDDEN_GRACE_DAYS_MIN,
            self::PHOTO_HIDDEN_GRACE_DAYS_MAX
        );
        $photoRetentionDays = $this->normalizeParameterInt(
            $_POST['photo_retention_days'] ?? null,
            self::PHOTO_RETENTION_DAYS_DEFAULT,
            self::PHOTO_RETENTION_DAYS_MIN,
            self::PHOTO_RETENTION_DAYS_MAX
        );

        $this->db->prepare("INSERT INTO parameters (param_key, param_value) VALUES ('email_mode',?) ON DUPLICATE KEY UPDATE param_value=?")
                 ->execute([$emailMode, $emailMode]);
        $this->db->prepare("INSERT INTO parameters (param_key, param_value) VALUES ('school_name',?) ON DUPLICATE KEY UPDATE param_value=?")
                 ->execute([$schoolName, $schoolName]);
        $this->db->prepare("INSERT INTO parameters (param_key, param_value) VALUES ('photo_hidden_grace_days',?) ON DUPLICATE KEY UPDATE param_value=?")
                 ->execute([(string)$photoHiddenGraceDays, (string)$photoHiddenGraceDays]);
        $this->db->prepare("INSERT INTO parameters (param_key, param_value) VALUES ('photo_retention_days',?) ON DUPLICATE KEY UPDATE param_value=?")
                 ->execute([(string)$photoRetentionDays, (string)$photoRetentionDays]);

        $this->redirect('/administration/setup-parameters?saved=1');
    }

    private function normalizeParameterInt(mixed $value, int $default, int $min, int $max): int {
        if (!is_numeric($value)) {
            return $default;
        }
        $val = (int)$value;
        if ($val < $min) {
            return $min;
        }
        if ($val > $max) {
            return $max;
        }
        return $val;
    }
}
