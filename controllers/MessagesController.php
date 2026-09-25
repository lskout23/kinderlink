<?php
class MessagesController extends Controller {
    private string $lastMailError = '';
    private const PHOTO_MAX_BYTES = 5242880; // 5 MB
    private const PHOTO_RETENTION_DAYS_DEFAULT = 180;
    private const PHOTO_RETENTION_DAYS_MIN = 1;
    private const PHOTO_RETENTION_DAYS_MAX = 730;

    private const PHOTO_HIDDEN_GRACE_DAYS_DEFAULT = 15;
    private const PHOTO_HIDDEN_GRACE_DAYS_MIN = 1;
    private const PHOTO_HIDDEN_GRACE_DAYS_MAX = 60;

    private ?bool $hasPhotosTable = null;
    private ?int $photoRetentionDays = null;
    private ?int $photoHiddenGraceDays = null;

    public function create(): void {
        Auth::requireRole('admin', 'teacher');

        $user = Auth::user();

        // Load groups (teacher only sees their groups)
        if (Auth::isAdmin()) {
            $groups = $this->db->query('SELECT id, name FROM `groups` WHERE is_current=1 ORDER BY name')->fetchAll();
        } else {
            $stmt = $this->db->prepare(
                'SELECT g.id, g.name FROM `groups` g
                 JOIN teacher_groups tg ON tg.group_id = g.id
                 WHERE tg.user_id = ? AND g.is_current = 1 ORDER BY g.name'
            );
            $stmt->execute([$user['id']]);
            $groups = $stmt->fetchAll();
        }

        // Activities for bulk-add panels
        $actBoth = $this->db->query(
            "SELECT id, name FROM activities WHERE type='both' ORDER BY sort_order, name"
        )->fetchAll();
        $actEmailOnly = $this->db->query(
            "SELECT id, name FROM activities WHERE type='email_only' ORDER BY sort_order, name"
        )->fetchAll();

        $this->render('messages/create', [
            'pageTitle'    => 'Δημιουργία Μηνυμάτων',
            'groups'       => $groups,
            'actBoth'      => $actBoth,
            'actEmailOnly' => $actEmailOnly,
            'isAdmin'      => (Auth::isAdmin() || Auth::isTeacher()),
            'photoHiddenGraceDays' => $this->getPhotoHiddenGraceDays(),
        ]);
    }

    /** Return children of a group for a given date with existing message data */
    public function apiListByGroup(): void {
        Auth::requireRole('admin', 'teacher');

        $groupId = (int)($_POST['group_id'] ?? 0);
        $date    = $_POST['date'] ?? date('Y-m-d');

        if ($groupId <= 0) { $this->json(['error' => 'Μη έγκυρο τμήμα.'], 422); return; }
        if (!$this->canAccessGroup($groupId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
            return;
        }

        if (!Attendance::validDate($date)) {
            $this->json(['error' => 'Μη έγκυρη ημερομηνία.'], 422);
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT c.id, c.first_name, c.last_name,
                    m.id AS msg_id, m.breakfast, m.lunch, m.mood, m.sleep_minutes,
                    m.wc, m.activities AS activities_txt, m.comments, m.email_status
             FROM children c
             JOIN children_groups cg ON cg.child_id = c.id
             LEFT JOIN messages m ON m.child_id = c.id AND DATE(m.message_date) = ?
             WHERE cg.group_id = ? AND c.active = 1
             ORDER BY c.last_name, c.first_name'
        );
        $stmt->execute([$date, $groupId]);
        $rows = $stmt->fetchAll();

        $attendanceReady = false;
        $absentIds = [];
        try {
            $attendance = new Attendance($this->db);
            $attendanceReady = $attendance->isReady();
            if ($attendanceReady) $absentIds = $attendance->absentIds($date);
        } catch (Throwable $e) {
            $attendanceReady = false;
        }
        foreach ($rows as &$row) {
            $row['is_absent'] = $attendanceReady ? in_array((int)$row['id'], $absentIds, true) : null;
        }
        unset($row);
        $this->json(['rows' => $rows, 'attendance_ready' => $attendanceReady,
            'attendance_error' => $attendanceReady ? '' : Attendance::UNAVAILABLE]);
    }

    /** Immediately persist one child's attendance, independently of message save. */
    public function apiAttendance(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();
        $groupId = filter_var($_POST['group_id'] ?? null, FILTER_VALIDATE_INT);
        $childId = filter_var($_POST['child_id'] ?? null, FILTER_VALIDATE_INT);
        $date = $_POST['date'] ?? null;
        $absent = $_POST['is_absent'] ?? null;
        if (!$groupId || $groupId < 1 || !$childId || $childId < 1 || !Attendance::validDate($date)
            || !in_array($absent, ['0', '1'], true)) {
            $this->json(['error' => 'Μη έγκυρα στοιχεία απουσίας.'], 422);
            return;
        }
        if (!$this->canAccessGroup($groupId) || !$this->childInGroup($childId, $groupId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στο παιδί / τμήμα.'], 403);
            return;
        }
        try {
            $attendance = new Attendance($this->db);
            if (!$attendance->isReady()) {
                $this->json(['error' => Attendance::UNAVAILABLE], 503);
                return;
            }
            $attendance->setAbsent($childId, $date, $absent === '1', (int)Auth::user()['id']);
            $this->json(['success' => true, 'child_id' => $childId, 'date' => $date, 'is_absent' => $absent === '1']);
        } catch (Throwable $e) {
            error_log('[attendance save] ' . get_class($e) . ' code=' . $e->getCode());
            $this->json(['error' => 'Η αποθήκευση απουσίας δεν επιβεβαιώθηκε. Φορτώστε ξανά το τμήμα πριν από αποστολή.'], 503);
        }
    }

    /** Save one or multiple message rows */
    public function apiSave(): void {
        try {
            Auth::requireRole('admin', 'teacher');
            $this->verifyCsrf();

            $groupId = (int)($_POST['group_id'] ?? 0);
            $date    = $_POST['date'] ?? date('Y-m-d');
            $rows    = $_POST['rows'] ?? []; // array of child message data

            if ($groupId <= 0) { $this->json(['error' => 'Μη έγκυρο τμήμα.'], 422); return; }
            if (!$this->canAccessGroup($groupId)) {
                $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
                return;
            }
            if (!Attendance::validDate($date)) {
                $this->json(['error' => 'Μη έγκυρη ημερομηνία.'], 422);
                return;
            }
            if (!is_array($rows)) { $this->json(['error' => 'Μη έγκυρα δεδομένα.'], 422); return; }

            // Ασφάλεια για μεγάλα payloads: αν το max_input_vars ξεπεράστηκε, τα rows κόπηκαν σιωπηλά.
            $expectedFieldsPerRow = 8; // child_id, breakfast, lunch, mood, sleep_minutes, wc, activities_txt, comments
            $rowsCount = count($rows);
            $maxInputVars = (int)ini_get('max_input_vars');
            if ($maxInputVars > 0 && ($rowsCount * $expectedFieldsPerRow + 8) >= $maxInputVars) {
                $this->json([
                    'error' => 'Το τμήμα έχει πάρα πολλά παιδιά για μία αποστολή. Επικοινωνήστε με τον διαχειριστή για αύξηση του PHP max_input_vars (τρέχον όριο: ' . $maxInputVars . ').',
                    'debug' => [
                        'rows_received' => $rowsCount,
                        'estimated_fields' => $rowsCount * $expectedFieldsPerRow + 8,
                        'max_input_vars' => $maxInputVars,
                    ],
                ], 413);
                return;
            }

            $saved = 0;
            foreach ($rows as $r) {
                $childId  = (int)($r['child_id']  ?? 0);
                $breakfast = max(0, min(4, (int)($r['breakfast'] ?? 0)));
                $lunch     = max(0, min(4, (int)($r['lunch']     ?? 0)));
                $mood      = max(0, min(4, (int)($r['mood']      ?? 0)));
                $sleep     = max(0, (int)($r['sleep_minutes'] ?? 0));
                $wc        = !empty($r['wc']) ? 1 : 0;
                $activities = trim($r['activities_txt'] ?? '');
                $comments   = trim($r['comments'] ?? '');

                if ($childId <= 0) continue;
                if (!$this->childInGroup($childId, $groupId)) continue;

                // Search by unique key columns only (child_id + date). Το uq_child_date
                // από την parent-threads-migration επιτρέπει μόνο 1 μήνυμα ανά (παιδί, ημέρα),
                // ανεξάρτητα από το τμήμα. Αν βρεθεί υπάρχον record, κάνουμε UPDATE και
                // ενημερώνουμε και το group_id ώστε να μεταφέρεται με το παιδί.
                $existing = $this->db->prepare(
                    'SELECT id FROM messages WHERE child_id=? AND DATE(message_date)=?'
                );
                $existing->execute([$childId, $date]);
                $msgId = $existing->fetchColumn();

                if ($msgId) {
                    $this->db->prepare(
                        'UPDATE messages SET group_id=?, breakfast=?, lunch=?, mood=?, sleep_minutes=?,
                         wc=?, activities=?, comments=?, email_status=\'pending\'
                         WHERE id=?'
                    )->execute([$groupId, $breakfast, $lunch, $mood, $sleep, $wc, $activities, $comments, $msgId]);
                } else {
                    $this->db->prepare(
                        'INSERT INTO messages (child_id, group_id, message_date, breakfast, lunch, mood,
                         sleep_minutes, wc, activities, comments, email_status)
                         VALUES (?,?,?,?,?,?,?,?,?,?,\'pending\')'
                    )->execute([$childId, $groupId, $date, $breakfast, $lunch, $mood, $sleep, $wc, $activities, $comments]);
                }
                $saved++;
            }

            if ($this->photosTableExists()) {
                try { $this->cleanupOldPhotos(); } catch (Throwable $e) {}
            }

            $this->json(['success' => true, 'saved' => $saved]);
        } catch (Throwable $e) {
            // Καταγραφή για δικές μας αναφορές
            error_log('[apiSave] ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            $debugPayload = null;
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $debugPayload = [
                    'file'  => basename($e->getFile()),
                    'line'  => $e->getLine(),
                    'class' => get_class($e),
                    'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5),
                ];
            }
            $this->json([
                'error' => 'Σφάλμα αποθήκευσης: ' . $e->getMessage(),
                'debug' => $debugPayload,
            ], 500);
        }
    }

    public function apiPurgeAllPhotos(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή.'], 422);
            return;
        }

        $base = $this->photoStorageDir();
        $stmt = $this->db->query('SELECT stored_name FROM message_photos');
        $rows = $stmt ? $stmt->fetchAll() : [];

        $deleted = 0;
        foreach ($rows as $row) {
            $path = $base . DIRECTORY_SEPARATOR . $row['stored_name'];
            if (is_file($path)) {
                @unlink($path);
                $deleted++;
            }
        }

        $this->db->exec('DELETE FROM message_photos');

        $this->json(['success' => true, 'deleted_files' => $deleted, 'deleted_records' => count($rows)]);
    }

    public function apiEmailPreview(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        $childId = (int)($_POST['child_id'] ?? 0);
        $groupId = (int)($_POST['group_id'] ?? 0);
        $date    = $_POST['date'] ?? date('Y-m-d');

        if ($childId <= 0 || $groupId <= 0) {
            $this->json(['error' => 'Μη έγκυρα στοιχεία.'], 422);
            return;
        }
        if (!$this->canAccessGroup($groupId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στο τμήμα.'], 403);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $tpl = $this->db->query('SELECT template_html, subject FROM email_template LIMIT 1')->fetch();
        if (!$tpl) {
            $this->json(['error' => 'Δεν υπάρχει πρότυπο email.'], 422);
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT m.*, c.first_name, c.last_name FROM messages m
             JOIN children c ON c.id = m.child_id
             WHERE m.child_id = ? AND DATE(m.message_date) = ?
             LIMIT 1'
        );
        $stmt->execute([$childId, $date]);
        $msg = $stmt->fetch();

        if (!$msg) {
            $this->json(['error' => 'Δεν βρέθηκε μήνυμα για αυτό το παιδί/ημερομηνία. Αποθηκεύστε πρώτα.'], 422);
            return;
        }

        $ratingLabels = ['0' => '-', '1' => 'Καθόλου', '2' => 'Μέτρια', '3' => 'Καλά', '4' => 'Πολύ Καλά'];
        $childName = $msg['first_name'] . ' ' . $msg['last_name'];

        $body = $this->buildEmailBody($tpl['template_html'], [
            '|*name*|'      => $childName,
            '|*breakfast*|' => $this->ratingBadge($ratingLabels[$msg['breakfast']] ?? '-', $this->ratingColor((int)$msg['breakfast'])),
            '|*lunch*|'     => $this->ratingBadge($ratingLabels[$msg['lunch']] ?? '-', $this->ratingColor((int)$msg['lunch'])),
            '|*mood*|'      => $this->ratingBadge($ratingLabels[$msg['mood']] ?? '-', $this->ratingColor((int)$msg['mood'])),
            '|*breakfast_text*|' => $ratingLabels[$msg['breakfast']] ?? '-',
            '|*lunch_text*|'     => $ratingLabels[$msg['lunch']] ?? '-',
            '|*mood_text*|'      => $ratingLabels[$msg['mood']] ?? '-',
            '|*breakfast_color*|' => $this->ratingColor((int)$msg['breakfast']),
            '|*lunch_color*|'     => $this->ratingColor((int)$msg['lunch']),
            '|*mood_color*|'      => $this->ratingColor((int)$msg['mood']),
            '|*breakfast_badge*|' => $this->ratingBadge($ratingLabels[$msg['breakfast']] ?? '-', $this->ratingColor((int)$msg['breakfast'])),
            '|*lunch_badge*|'     => $this->ratingBadge($ratingLabels[$msg['lunch']] ?? '-', $this->ratingColor((int)$msg['lunch'])),
            '|*mood_badge*|'      => $this->ratingBadge($ratingLabels[$msg['mood']] ?? '-', $this->ratingColor((int)$msg['mood'])),
            '|*sleep*|'     => $msg['sleep_minutes'] > 0 ? (string)$msg['sleep_minutes'] : 'Όχι',
            '|*WC_txt*|'    => $msg['wc'] ? 'Ναι' : 'Όχι',
            '|*activity*|'  => $msg['activities'],
            '|*comments*|'  => $msg['comments'],
        ]);

        $subject = str_replace('|*name*|', $childName, $tpl['subject']);

        $this->json(['html' => '<div style="font-size:12px;color:#6b7280;margin-bottom:8px;">Θέμα: <strong>' . htmlspecialchars($subject) . '</strong></div>' . $body]);
    }

    /** Send emails for all children of a group on a date */
    public function apiSendEmails(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        $date    = $_POST['date'] ?? date('Y-m-d');

        // Legacy open tabs can exclude additional children, never override DB absence.
        $excludedChildIds = $_POST['excluded_child_ids'] ?? [];
        if (!is_array($excludedChildIds)) { $excludedChildIds = []; }
        $excludedMap = [];
        foreach ($excludedChildIds as $id) {
            $childId = (int)$id;
            if ($childId > 0) $excludedMap[$childId] = true;
        }

        if ($groupId <= 0) { $this->json(['error' => 'Μη έγκυρο τμήμα.'], 422); return; }
        if (!$this->canAccessGroup($groupId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
            return;
        }
        if (!Attendance::validDate($date)) {
            $this->json(['error' => 'Μη έγκυρη ημερομηνία.'], 422);
            return;
        }

        $attendance = new Attendance($this->db);
        try {
            if (!$attendance->isReady()) {
                $this->json(['error' => Attendance::UNAVAILABLE], 503);
                return;
            }
        } catch (Throwable $e) {
            $this->json(['error' => Attendance::UNAVAILABLE], 503);
            return;
        }
        if ($this->photosTableExists()) $this->cleanupOldPhotos();

        // Get email mode
        $mode = $this->db->query("SELECT param_value FROM parameters WHERE param_key='email_mode'")->fetchColumn();
        $mode = $mode ?: 'virtual';

        // Get template
        $tpl = $this->db->query('SELECT template_html, subject FROM email_template LIMIT 1')->fetch();
        if (!$tpl) { $this->json(['error' => 'Δεν υπάρχει πρότυπο email.'], 422); return; }

        // Get messages for the group/date
        $stmt = $this->db->prepare(
            'SELECT m.*, c.first_name, c.last_name, c.email1, c.email2, c.send_email1, c.send_email2
             FROM messages m
             JOIN children c ON c.id = m.child_id
                         WHERE m.group_id = ? AND DATE(m.message_date) = ?
                             AND m.email_status IN (\'pending\', \'failed\', \'virtual\')'
        );
        $stmt->execute([$groupId, $date]);
        $messages = $stmt->fetchAll();

        $ratingLabels = ['0' => '-', '1' => 'Καθόλου', '2' => 'Μέτρια', '3' => 'Καλά', '4' => 'Πολύ Καλά'];
        $sent = 0; $failed = 0; $skipped = 0;
        $firstError = '';

        foreach ($messages as $msg) {
            $childId = (int)($msg['child_id'] ?? 0);
            try {
                $isAbsent = $attendance->isAbsent($childId, $date);
            } catch (Throwable $e) {
                $this->json(['error' => 'Η αποστολή διακόπηκε: δεν ήταν δυνατός ο έλεγχος απουσιών. Ελέγξτε τη λίστα μηνυμάτων πριν επαναλάβετε.',
                    'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped], 503);
                return;
            }
            if ($isAbsent || !empty($excludedMap[$childId])) {
                // Keep saved content and status, so undoing absence permits a later send.
                $skipped++;
                continue;
            }

            $recipients = [];
            if ($msg['send_email1'] && $msg['email1']) $recipients[] = $msg['email1'];
            if ($msg['send_email2'] && $msg['email2']) $recipients[] = $msg['email2'];

            $breakfastLabel = $ratingLabels[$msg['breakfast']] ?? '-';
            $lunchLabel = $ratingLabels[$msg['lunch']] ?? '-';
            $moodLabel = $ratingLabels[$msg['mood']] ?? '-';
            $breakfastColor = $this->ratingColor((int)$msg['breakfast']);
            $lunchColor = $this->ratingColor((int)$msg['lunch']);
            $moodColor = $this->ratingColor((int)$msg['mood']);

            $childName = $msg['first_name'] . ' ' . $msg['last_name'];
            $body = $this->buildEmailBody($tpl['template_html'], [
                '|*name*|'      => $childName,
                '|*breakfast*|' => $this->ratingBadge($breakfastLabel, $breakfastColor),
                '|*lunch*|'     => $this->ratingBadge($lunchLabel, $lunchColor),
                '|*mood*|'      => $this->ratingBadge($moodLabel, $moodColor),
                '|*breakfast_text*|' => $breakfastLabel,
                '|*lunch_text*|'     => $lunchLabel,
                '|*mood_text*|'      => $moodLabel,
                '|*breakfast_color*|' => $breakfastColor,
                '|*lunch_color*|'     => $lunchColor,
                '|*mood_color*|'      => $moodColor,
                '|*breakfast_badge*|' => $this->ratingBadge($breakfastLabel, $breakfastColor),
                '|*lunch_badge*|'     => $this->ratingBadge($lunchLabel, $lunchColor),
                '|*mood_badge*|'      => $this->ratingBadge($moodLabel, $moodColor),
                '|*sleep*|'     => $msg['sleep_minutes'] > 0 ? (string)$msg['sleep_minutes'] : 'Όχι',
                '|*WC_txt*|'    => $msg['wc'] ? 'Ναι' : 'Όχι',
                '|*activity*|'  => $msg['activities'],
                '|*comments*|'  => $msg['comments'],
            ]);
            $subject = str_replace('|*name*|', $childName, $tpl['subject']);

            if ($this->photosTableExists()) {
                $photos = $this->getMessagePhotos((int)$msg['child_id'], (int)$msg['group_id'], (string)$msg['message_date']);
                if (!empty($photos)) {
                    $body .= $this->buildPhotoZipHtml(
                        $photos,
                        $childName,
                        (string)$msg['message_date']
                    );
                }
            }

            $status = 'virtual';
            if ($mode === 'real') {
                if (empty($recipients)) {
                    $status = 'failed';
                    $failed++;
                    if ($firstError === '') $firstError = 'Δεν υπάρχουν ενεργοί παραλήπτες για το παιδί (send_email1/send_email2).';
                } else {
                    $recipientSuccess = 0;
                    $recipientFailed = 0;
                    foreach (array_values(array_unique($recipients)) as $recipient) {
                        if ($this->sendEmail([$recipient], $subject, $body)) {
                            $recipientSuccess++;
                            $sent++;
                        } else {
                            $recipientFailed++;
                            $failed++;
                            if ($firstError === '') $firstError = $this->lastMailError !== '' ? $this->lastMailError : 'Αποτυχία SMTP αποστολής.';
                        }
                    }
                    $status = ($recipientSuccess > 0 && $recipientFailed === 0) ? 'sent' : 'failed';
                }
            } else {
                $status = 'virtual';
                $sent++;
            }

            $this->db->prepare('UPDATE messages SET email_status=? WHERE id=?')
                     ->execute([$status, $msg['id']]);
        }

        $this->json([
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'mode' => $mode,
            'error_detail' => $firstError,
            'processed' => count($messages),
        ]);
    }

    public function apiPhotosList(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $this->cleanupOldPhotos();

        $groupId = (int)($_POST['group_id'] ?? 0);
        $childId = (int)($_POST['child_id'] ?? 0);
        $date    = $_POST['date'] ?? date('Y-m-d');
        $includeHidden = (Auth::isAdmin() || Auth::isTeacher()) && !empty($_POST['include_hidden']);
        $allDates = !empty($_POST['all_dates']);

        if ($groupId <= 0 || $childId <= 0) {
            $this->json(['error' => 'Μη έγκυρα στοιχεία παιδιού/τμήματος.'], 422);
            return;
        }
        if (!$this->canAccessGroup($groupId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        if (!$this->childInGroup($childId, $groupId)) {
            $this->json(['error' => 'Το παιδί δεν ανήκει στο επιλεγμένο τμήμα.'], 422);
            return;
        }

        $sql = 'SELECT id, original_name, mime_type, size_bytes, access_token, created_at, hidden_at, message_date
            FROM message_photos
            WHERE child_id = ? AND group_id = ?';
        $params = [$childId, $groupId];
        if (!$allDates) {
            $sql .= ' AND message_date = ?';
            $params[] = $date;
        }
        if (!$includeHidden) {
            $sql .= ' AND hidden_at IS NULL';
        }
        $sql .= ' ORDER BY id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['url'] = $this->photoPublicUrl($row['access_token']);
            $rows[] = $row;
        }

        $this->json(['rows' => $rows]);
    }

    public function apiPhotosUpload(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $this->cleanupOldPhotos();

        $groupId = (int)($_POST['group_id'] ?? 0);
        $childId = (int)($_POST['child_id'] ?? 0);
        $date    = $_POST['date'] ?? date('Y-m-d');
        $forceOverwrite = !empty($_POST['force_overwrite']);
        $skipDuplicates = !empty($_POST['skip_duplicates']);

        if ($groupId <= 0 || $childId <= 0) {
            $this->json(['error' => 'Μη έγκυρα στοιχεία παιδιού/τμήματος.'], 422);
            return;
        }
        if (!$this->canAccessGroup($groupId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        if (!$this->childInGroup($childId, $groupId)) {
            $this->json(['error' => 'Το παιδί δεν ανήκει στο επιλεγμένο τμήμα.'], 422);
            return;
        }
        if (!isset($_FILES['photos'])) {
            $this->json(['error' => 'Δεν στάλθηκαν αρχεία.'], 422);
            return;
        }

        $files = $this->normalizeFilesArray($_FILES['photos']);
        if (empty($files)) {
            $this->json(['error' => 'Δεν βρέθηκαν αρχεία για αποστολή.'], 422);
            return;
        }

        $incomingNames = [];
        foreach ($files as $file) {
            $name = trim((string)($file['name'] ?? ''));
            if ($name !== '') {
                $incomingNames[$name] = true;
            }
        }

        $duplicateNamesMap = [];
        if (!empty($incomingNames)) {
            $nameList = array_keys($incomingNames);
            $placeholders = implode(',', array_fill(0, count($nameList), '?'));
            $params = [$childId, $groupId];
            foreach ($nameList as $n) {
                $params[] = mb_substr($n, 0, 255, 'UTF-8');
            }

            $dupStmt = $this->db->prepare(
                'SELECT id, stored_name, original_name, message_date
                 FROM message_photos
                 WHERE child_id = ? AND group_id = ?
                   AND original_name IN (' . $placeholders . ')'
            );
            $dupStmt->execute($params);
            $dupRows = $dupStmt->fetchAll();

            if (!empty($dupRows) && !$forceOverwrite) {
                $dupNames = [];
                $dupDetails = [];
                foreach ($dupRows as $d) {
                    $dupNames[(string)$d['original_name']] = true;
                    $dupDetails[] = (string)$d['original_name'] . ' (' . (string)$d['message_date'] . ')';
                }
                if (!$skipDuplicates) {
                    $this->json([
                        'error' => 'Υπάρχουν ήδη φωτογραφίες με ίδιο όνομα (και σε παλιότερες ημερομηνίες).',
                        'error_code' => 'PHOTO_NAME_EXISTS',
                        'duplicates' => array_keys($dupNames),
                        'duplicates_details' => $dupDetails,
                    ], 409);
                    return;
                }
                $duplicateNamesMap = $dupNames;
            }

            if (!empty($dupRows) && $forceOverwrite) {
                $base = $this->photoStorageDir();
                $ids = [];
                foreach ($dupRows as $d) {
                    $ids[] = (int)$d['id'];
                    $path = $base . DIRECTORY_SEPARATOR . (string)$d['stored_name'];
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
                if (!empty($ids)) {
                    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                    $delStmt = $this->db->prepare('DELETE FROM message_photos WHERE id IN (' . $idPlaceholders . ')');
                    $delStmt->execute($ids);
                }
            }
        }

        $uploadDir = $this->photoStorageDir();
        $createdBy = Auth::user()['id'] ?? null;
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        $saved = 0;
        $errors = [];
        $skippedDuplicates = [];
        $savedRows = [];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($files as $file) {
            $name = (string)($file['name'] ?? '');
            $tmpName = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $err  = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            $safeOriginalName = mb_substr(trim($name), 0, 255, 'UTF-8');

            if (!empty($duplicateNamesMap) && isset($duplicateNamesMap[$safeOriginalName])) {
                $skippedDuplicates[] = ($name !== '' ? $name : $safeOriginalName);
                continue;
            }

            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = ($name !== '' ? $name : 'Αρχείο') . ': αποτυχία μεταφόρτωσης (' . $err . ').';
                continue;
            }

            if ($size <= 0) {
                $errors[] = ($name !== '' ? $name : 'Αρχείο') . ': κενό αρχείο.';
                continue;
            }

            if ($size > self::PHOTO_MAX_BYTES) {
                $errors[] = ($name !== '' ? $name : 'Αρχείο') . ': υπερβαίνει το όριο 5MB.';
                continue;
            }

            $mime = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
            if (!isset($allowedMimes[$mime])) {
                $errors[] = ($name !== '' ? $name : 'Αρχείο') . ': μη υποστηριζόμενος τύπος.';
                continue;
            }

            $ext = $allowedMimes[$mime];
            $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $target = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

            if (!@move_uploaded_file($tmpName, $target)) {
                $errors[] = ($name !== '' ? $name : 'Αρχείο') . ': δεν αποθηκεύτηκε στον server.';
                if ($saved === 0) {
                    $freeSpace = @disk_free_space($uploadDir);
                    $extra = is_numeric($freeSpace) ? ' Διαθέσιμος χώρος: ' . $this->formatBytes((int)$freeSpace) . '.' : '';
                    $this->json([
                        'error' => 'Ο server δεν μπόρεσε να αποθηκεύσει τη φωτογραφία. Πιθανή έλλειψη χώρου ή πρόβλημα στο storage.' . $extra,
                        'error_code' => 'PHOTO_STORAGE_FAILED',
                    ], 507);
                    return;
                }
                continue;
            }

            $token = bin2hex(random_bytes(24));
            $stmt = $this->db->prepare(
                'INSERT INTO message_photos
                 (child_id, group_id, message_date, stored_name, original_name, mime_type, size_bytes, access_token, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $childId,
                $groupId,
                $date,
                $storedName,
                mb_substr($name !== '' ? $name : $storedName, 0, 255, 'UTF-8'),
                $mime,
                $size,
                $token,
                $createdBy,
            ]);

            $saved++;
            $savedRows[] = [
                'id' => (int)$this->db->lastInsertId(),
                'original_name' => $name !== '' ? $name : $storedName,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'url' => $this->photoPublicUrl($token),
            ];
        }

        if ($finfo) {
            finfo_close($finfo);
        }

        $this->json([
            'success' => true,
            'saved' => $saved,
            'errors' => $errors,
            'skipped_duplicates_count' => count($skippedDuplicates),
            'skipped_duplicates' => $skippedDuplicates,
            'rows' => $savedRows,
        ]);
    }

    public function apiPhotosUploadGroup(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $this->cleanupOldPhotos();

        $groupId = (int)($_POST['group_id'] ?? 0);
        $date    = $_POST['date'] ?? date('Y-m-d');
        $forceOverwrite = !empty($_POST['force_overwrite']);
        $skipDuplicates = !empty($_POST['skip_duplicates']);
        $requestedChildIds = $this->normalizedPhotoIds($_POST['child_ids'] ?? []);

        if ($groupId <= 0) {
            $this->json(['error' => 'Μη έγκυρο τμήμα.'], 422);
            return;
        }
        if (!$this->canAccessGroup($groupId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        if (!isset($_FILES['photos'])) {
            $this->json(['error' => 'Δεν στάλθηκαν αρχεία.'], 422);
            return;
        }

        $childrenSql =
            'SELECT c.id, c.first_name, c.last_name
             FROM children c
             JOIN children_groups cg ON cg.child_id = c.id
             WHERE cg.group_id = ? AND c.active = 1';
        $childrenParams = [$groupId];

        if (!empty($requestedChildIds)) {
            $requestedPlaceholders = implode(',', array_fill(0, count($requestedChildIds), '?'));
            $childrenSql .= ' AND c.id IN (' . $requestedPlaceholders . ')';
            foreach ($requestedChildIds as $rid) {
                $childrenParams[] = $rid;
            }
        }

        $childrenSql .= ' ORDER BY c.id';
        $childrenStmt = $this->db->prepare($childrenSql);
        $childrenStmt->execute($childrenParams);
        $childrenRows = $childrenStmt->fetchAll();
        $childIds = array_map('intval', array_column($childrenRows, 'id'));
        $childLabelMap = [];
        foreach ($childrenRows as $childRow) {
            $childId = (int)$childRow['id'];
            $childLabel = trim((string)($childRow['last_name'] ?? '') . ' ' . (string)($childRow['first_name'] ?? ''));
            if ($childLabel === '') {
                $childLabel = 'Παιδί #' . $childId;
            }
            $childLabelMap[$childId] = $childLabel;
        }

        if (empty($childIds)) {
            $this->json(['error' => !empty($requestedChildIds)
                ? 'Δεν βρέθηκαν έγκυρα επιλεγμένα παιδιά στο τμήμα.'
                : 'Δεν υπάρχουν ενεργά παιδιά στο επιλεγμένο τμήμα.'], 422);
            return;
        }

        $files = $this->normalizeFilesArray($_FILES['photos']);
        if (empty($files)) {
            $this->json(['error' => 'Δεν βρέθηκαν αρχεία για αποστολή.'], 422);
            return;
        }

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        $preparedFiles = [];
        $errors = [];
        $incomingNames = [];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($files as $file) {
            $name = (string)($file['name'] ?? '');
            $tmpName = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $err  = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = ($name !== '' ? $name : 'Αρχείο') . ': αποτυχία μεταφόρτωσης (' . $err . ').';
                continue;
            }
            if ($size <= 0) {
                $errors[] = ($name !== '' ? $name : 'Αρχείο') . ': κενό αρχείο.';
                continue;
            }
            if ($size > self::PHOTO_MAX_BYTES) {
                $errors[] = ($name !== '' ? $name : 'Αρχείο') . ': υπερβαίνει το όριο 5MB.';
                continue;
            }

            $mime = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
            if (!isset($allowedMimes[$mime])) {
                $errors[] = ($name !== '' ? $name : 'Αρχείο') . ': μη υποστηριζόμενος τύπος.';
                continue;
            }

            $safeName = mb_substr($name !== '' ? $name : ('photo.' . $allowedMimes[$mime]), 0, 255, 'UTF-8');
            $incomingNames[$safeName] = true;
            $preparedFiles[] = [
                'original_name' => $safeName,
                'tmp_name' => $tmpName,
                'size_bytes' => $size,
                'mime_type' => $mime,
                'ext' => $allowedMimes[$mime],
            ];
        }

        if ($finfo) {
            finfo_close($finfo);
        }

        if (empty($preparedFiles)) {
            $this->json([
                'error' => 'Δεν βρέθηκαν έγκυρες εικόνες για αποστολή.',
                'errors' => $errors,
            ], 422);
            return;
        }

        $duplicatePairMap = [];
        if (!empty($incomingNames)) {
            $nameList = array_keys($incomingNames);
            $namePlaceholders = implode(',', array_fill(0, count($nameList), '?'));
            $childPlaceholders = implode(',', array_fill(0, count($childIds), '?'));

            $params = [$groupId];
            foreach ($childIds as $cid) {
                $params[] = $cid;
            }
            foreach ($nameList as $n) {
                $params[] = $n;
            }

            $dupStmt = $this->db->prepare(
                'SELECT id, child_id, stored_name, original_name, message_date
                 FROM message_photos
                 WHERE group_id = ?
                   AND child_id IN (' . $childPlaceholders . ')
                   AND original_name IN (' . $namePlaceholders . ')'
            );
            $dupStmt->execute($params);
            $dupRows = $dupStmt->fetchAll();

            if (!empty($dupRows) && !$forceOverwrite) {
                $dupDetails = [];
                foreach ($dupRows as $d) {
                    if (count($dupDetails) >= 20) {
                        break;
                    }
                    $dupChildId = (int)$d['child_id'];
                    $dupChildLabel = $childLabelMap[$dupChildId] ?? ('Παιδί #' . $dupChildId);
                    $dupDetails[] = (string)$d['original_name'] . ' (' . $dupChildLabel . ', ' . (string)$d['message_date'] . ')';
                }
                if (!$skipDuplicates) {
                    $this->json([
                        'error' => 'Βρέθηκαν διπλότυπα ονόματα φωτογραφιών στο τμήμα.',
                        'error_code' => 'PHOTO_NAME_EXISTS_GROUP',
                        'duplicates_details' => $dupDetails,
                        'duplicates_count' => count($dupRows),
                    ], 409);
                    return;
                }

                foreach ($dupRows as $d) {
                    $pairKey = (int)$d['child_id'] . '|' . (string)$d['original_name'];
                    $duplicatePairMap[$pairKey] = true;
                }
            }

            if (!empty($dupRows) && $forceOverwrite) {
                $base = $this->photoStorageDir();
                $ids = [];
                foreach ($dupRows as $d) {
                    $ids[] = (int)$d['id'];
                    $path = $base . DIRECTORY_SEPARATOR . (string)$d['stored_name'];
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
                if (!empty($ids)) {
                    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                    $delStmt = $this->db->prepare('DELETE FROM message_photos WHERE id IN (' . $idPlaceholders . ')');
                    $delStmt->execute($ids);
                }
            }
        }

        $uploadDir = $this->photoStorageDir();
        $createdBy = Auth::user()['id'] ?? null;
        $saved = 0;
        $skippedDuplicates = [];

        foreach ($childIds as $cid) {
            $childLabel = $childLabelMap[$cid] ?? ('Παιδί #' . $cid);
            foreach ($preparedFiles as $pf) {
                $pairKey = $cid . '|' . $pf['original_name'];
                if (!empty($duplicatePairMap) && isset($duplicatePairMap[$pairKey])) {
                    $skippedDuplicates[] = $pf['original_name'] . ' (' . $childLabel . ')';
                    continue;
                }

                $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $pf['ext'];
                $target = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

                if (!@copy($pf['tmp_name'], $target)) {
                    $errors[] = $pf['original_name'] . ' (' . $childLabel . '): δεν αποθηκεύτηκε στον server.';
                    if ($saved === 0) {
                        $freeSpace = @disk_free_space($uploadDir);
                        $extra = is_numeric($freeSpace) ? ' Διαθέσιμος χώρος: ' . $this->formatBytes((int)$freeSpace) . '.' : '';
                        $this->json([
                            'error' => 'Ο server δεν μπόρεσε να αποθηκεύσει τη φωτογραφία. Πιθανή έλλειψη χώρου ή πρόβλημα στο storage.' . $extra,
                            'error_code' => 'PHOTO_STORAGE_FAILED',
                        ], 507);
                        return;
                    }
                    continue;
                }

                $token = bin2hex(random_bytes(24));
                $stmt = $this->db->prepare(
                    'INSERT INTO message_photos
                     (child_id, group_id, message_date, stored_name, original_name, mime_type, size_bytes, access_token, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $cid,
                    $groupId,
                    $date,
                    $storedName,
                    $pf['original_name'],
                    $pf['mime_type'],
                    $pf['size_bytes'],
                    $token,
                    $createdBy,
                ]);

                $saved++;
            }
        }

        $this->json([
            'success' => true,
            'saved' => $saved,
            'children_count' => count($childIds),
            'files_count' => count($preparedFiles),
            'selected_mode' => !empty($requestedChildIds),
            'skipped_duplicates_count' => count($skippedDuplicates),
            'skipped_duplicates' => $skippedDuplicates,
            'errors' => $errors,
        ]);
    }

    public function apiPhotosDelete(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['error' => 'Μη έγκυρο ID φωτογραφίας.'], 422);
            return;
        }

        $row = $this->getAccessiblePhotoById($id);
        if (!$row) {
            $this->json(['error' => 'Η φωτογραφία δεν βρέθηκε ή δεν έχετε πρόσβαση.'], 404);
            return;
        }

        if (!empty($row['hidden_at'])) {
            $this->json(['success' => true, 'hidden' => false, 'already_hidden' => true]);
            return;
        }

        $this->db->prepare('UPDATE message_photos SET hidden_at = NOW() WHERE id = ?')->execute([$id]);
        $this->json(['success' => true, 'hidden' => true]);
    }

    public function apiPhotosDeleteSelected(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $ids = $this->normalizedPhotoIds($_POST['ids'] ?? []);
        if (empty($ids)) {
            $this->json(['error' => 'Δεν επιλέχθηκαν φωτογραφίες.'], 422);
            return;
        }

        $ids = $this->filterAccessiblePhotoIds($ids);
        if (empty($ids)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στις επιλεγμένες φωτογραφίες.'], 403);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stateStmt = $this->db->prepare(
            'SELECT id, hidden_at
             FROM message_photos
             WHERE id IN (' . $placeholders . ')'
        );
        $stateStmt->execute($ids);
        $rows = $stateStmt->fetchAll();

        $alreadyHidden = 0;
        foreach ($rows as $row) {
            if (!empty($row['hidden_at'])) {
                $alreadyHidden++;
            }
        }

        $stmt = $this->db->prepare(
            'UPDATE message_photos
             SET hidden_at = NOW()
             WHERE id IN (' . $placeholders . ') AND hidden_at IS NULL'
        );
        $stmt->execute($ids);
        $hiddenNow = (int)$stmt->rowCount();
        $this->json([
            'success' => true,
            'hidden' => $hiddenNow,
            'hidden_now' => $hiddenNow,
            'already_hidden_count' => $alreadyHidden,
            'selected_count' => count($rows),
        ]);
    }

    public function apiPhotosUnhide(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['error' => 'Μη έγκυρο ID φωτογραφίας.'], 422);
            return;
        }

        $row = $this->getAccessiblePhotoById($id);
        if (!$row) {
            $this->json(['error' => 'Η φωτογραφία δεν βρέθηκε ή δεν έχετε πρόσβαση.'], 404);
            return;
        }

        if (empty($row['hidden_at'])) {
            $this->json(['success' => true, 'unhidden' => false, 'already_visible' => true]);
            return;
        }

        $this->db->prepare('UPDATE message_photos SET hidden_at = NULL WHERE id = ?')->execute([$id]);
        $this->json(['success' => true, 'unhidden' => true]);
    }

    public function apiPhotosClearDay(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $groupId = (int)($_POST['group_id'] ?? 0);
        $date    = $_POST['date'] ?? date('Y-m-d');

        if ($groupId <= 0) {
            $this->json(['error' => 'Μη έγκυρο τμήμα.'], 422);
            return;
        }
        if (!$this->canAccessGroup($groupId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $stmt = $this->db->prepare(
            'UPDATE message_photos
             SET hidden_at = NOW()
             WHERE group_id = ? AND message_date = ? AND hidden_at IS NULL'
        );
        $stmt->execute([$groupId, $date]);
        $affected = $stmt->rowCount();

        if ($affected <= 0) {
            $this->json(['success' => true, 'deleted' => 0]);
            return;
        }

        $this->json(['success' => true, 'deleted' => $affected]);
    }

    public function apiPhotosPurge(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['error' => 'Μη έγκυρο ID φωτογραφίας.'], 422);
            return;
        }

        $row = $this->getAccessiblePhotoById($id);
        if (!$row) {
            $this->json(['error' => 'Η φωτογραφία δεν βρέθηκε ή δεν έχετε πρόσβαση.'], 404);
            return;
        }

        $path = $this->photoStorageDir() . DIRECTORY_SEPARATOR . $row['stored_name'];
        if (is_file($path)) {
            @unlink($path);
        }

        $this->db->prepare('DELETE FROM message_photos WHERE id = ?')->execute([$id]);
        $this->json(['success' => true, 'purged' => 1]);
    }

    public function apiPhotosPurgeSelected(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $ids = $this->normalizedPhotoIds($_POST['ids'] ?? []);
        if (empty($ids)) {
            $this->json(['error' => 'Δεν επιλέχθηκαν φωτογραφίες.'], 422);
            return;
        }

        $ids = $this->filterAccessiblePhotoIds($ids);
        if (empty($ids)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στις επιλεγμένες φωτογραφίες.'], 403);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare('SELECT id, stored_name FROM message_photos WHERE id IN (' . $placeholders . ')');
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            $this->json(['success' => true, 'purged' => 0]);
            return;
        }

        $base = $this->photoStorageDir();
        $deleteIds = [];
        foreach ($rows as $row) {
            $deleteIds[] = (int)$row['id'];
            $path = $base . DIRECTORY_SEPARATOR . $row['stored_name'];
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $delPlaceholders = implode(',', array_fill(0, count($deleteIds), '?'));
        $del = $this->db->prepare('DELETE FROM message_photos WHERE id IN (' . $delPlaceholders . ')');
        $del->execute($deleteIds);

        $this->json(['success' => true, 'purged' => count($deleteIds)]);
    }

    public function apiPhotosPurgeDay(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        if (!$this->photosTableExists()) {
            $this->json(['error' => 'Η λειτουργία φωτογραφιών δεν είναι ενεργή. Εκτελέστε πρώτα το migration.'], 422);
            return;
        }

        $groupId = (int)($_POST['group_id'] ?? 0);
        $childId = (int)($_POST['child_id'] ?? 0);
        $date    = $_POST['date'] ?? date('Y-m-d');

        if ($groupId <= 0) {
            $this->json(['error' => 'Μη έγκυρο τμήμα.'], 422);
            return;
        }
        if (!$this->canAccessGroup($groupId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        if ($childId > 0 && !$this->childInGroup($childId, $groupId)) {
            $this->json(['error' => 'Το παιδί δεν ανήκει στο επιλεγμένο τμήμα.'], 422);
            return;
        }

        $sql = 'SELECT id, stored_name FROM message_photos WHERE group_id = ? AND message_date = ?';
        $params = [$groupId, $date];
        if ($childId > 0) {
            $sql .= ' AND child_id = ?';
            $params[] = $childId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            $this->json(['success' => true, 'purged' => 0]);
            return;
        }

        $base = $this->photoStorageDir();
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row['id'];
            $path = $base . DIRECTORY_SEPARATOR . $row['stored_name'];
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $del = $this->db->prepare('DELETE FROM message_photos WHERE id IN (' . $placeholders . ')');
        $del->execute($ids);

        $this->json(['success' => true, 'purged' => count($ids)]);
    }

    public function photo(): void {
        if (!$this->photosTableExists()) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $token = trim((string)($_GET['t'] ?? ''));
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $retentionDays = $this->getPhotoRetentionDays();
        $hiddenGraceDays = $this->getPhotoHiddenGraceDays();

        $stmt = $this->db->prepare(
            'SELECT stored_name, original_name, mime_type, size_bytes
             FROM message_photos
             WHERE access_token = ?
                            AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)
                            AND (hidden_at IS NULL OR hidden_at >= DATE_SUB(NOW(), INTERVAL ' . $hiddenGraceDays . ' DAY))
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $path = $this->photoStorageDir() . DIRECTORY_SEPARATOR . $row['stored_name'];
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $mime = trim((string)$row['mime_type']) !== '' ? (string)$row['mime_type'] : 'application/octet-stream';
        $size = (int)filesize($path);
            $downloadName = str_replace(["\r", "\n", "\\", "/", '"'], '_', (string)$row['original_name']);
        if ($downloadName === '') {
            $downloadName = 'photo';
        }

        $download = (string)($_GET['d'] ?? '') === '1';
        $disposition = $download ? 'attachment' : 'inline';

        // Ensure no buffered/BOM output corrupts binary image bytes.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        @ini_set('zlib.output_compression', '0');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        // Remove all previously set headers (CSP, X-Frame-Options, etc.) so they
        // do not interfere with binary content delivery on shared hosting servers.
        header_remove();

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function photoView(): void {
        if (!$this->photosTableExists()) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $token = trim((string)($_GET['t'] ?? ''));
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $retentionDays = $this->getPhotoRetentionDays();
        $hiddenGraceDays = $this->getPhotoHiddenGraceDays();

        $stmt = $this->db->prepare(
            'SELECT original_name
             FROM message_photos
             WHERE access_token = ?
                            AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)
                            AND (hidden_at IS NULL OR hidden_at >= DATE_SUB(NOW(), INTERVAL ' . $hiddenGraceDays . ' DAY))
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $name = $this->e((string)$row['original_name']);
        // Use route-relative URLs to avoid BASE_URL mismatches across root/subfolder deployments.
        $imgUrl = $this->e('photo?t=' . rawurlencode($token));
        $downloadUrl = $this->e('photo?t=' . rawurlencode($token) . '&d=1');

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="el"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Φωτογραφία - ' . $name . '</title>'
            . '<style>'
            . 'body{margin:0;font-family:Arial,sans-serif;background:#f4f6f8;color:#1f2937;}'
            . '.wrap{max-width:1200px;margin:0 auto;padding:16px;}'
            . '.bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;}'
            . '.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:8px 12px;border-radius:8px;font-weight:600;font-size:14px;}'
            . '.meta{font-size:14px;color:#374151;font-weight:600;}'
            . '.card{background:#fff;border:1px solid #d1d5db;border-radius:10px;padding:10px;box-shadow:0 2px 10px rgba(0,0,0,.06);}'
            . '.card img{display:block;max-width:100%;height:auto;margin:0 auto;border-radius:6px;}'
            . '.hint{margin-top:8px;font-size:12px;color:#6b7280;}'
            . '</style></head><body><div class="wrap">'
            . '<div class="bar"><a class="btn" href="' . $downloadUrl . '">Λήψη φωτογραφίας</a><span class="meta">' . $name . '</span></div>'
            . '<div class="card"><img src="' . $imgUrl . '" alt="' . $name . '"></div>'
            . '<div class="hint">Αν η εικόνα φαίνεται μικρή, κάντε zoom στον browser.</div>'
            . '</div></body></html>';
        exit;
    }

    public function photoZip(): void {
        if (!$this->photosTableExists()) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $tokenParam = trim((string)($_GET['t'] ?? ''));
        if ($tokenParam === '') {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $tokens = array_values(array_unique(array_filter(array_map('trim', explode(',', $tokenParam)))));
        $tokens = array_values(array_filter($tokens, function($token) {
            return preg_match('/^[a-f0-9]{48}$/', $token);
        }));

        if (empty($tokens)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $retentionDays = $this->getPhotoRetentionDays();
        $hiddenGraceDays = $this->getPhotoHiddenGraceDays();
        $placeholders = implode(',', array_fill(0, count($tokens), '?'));

        $stmt = $this->db->prepare(
            'SELECT stored_name, original_name, access_token, child_id, group_id, message_date
             FROM message_photos
             WHERE access_token IN (' . $placeholders . ')
               AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)
               AND (hidden_at IS NULL OR hidden_at >= DATE_SUB(NOW(), INTERVAL ' . $hiddenGraceDays . ' DAY))'
        );
        $stmt->execute($tokens);
        $rows = $stmt->fetchAll();

        if (count($rows) !== count($tokens)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $first = $rows[0] ?? null;
        if (!$first) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        foreach ($rows as $row) {
            if ((int)$row['child_id'] !== (int)$first['child_id']
                || (int)$row['group_id'] !== (int)$first['group_id']
                || substr((string)$row['message_date'], 0, 10) !== substr((string)$first['message_date'], 0, 10)) {
                http_response_code(404);
                echo 'Not found';
                return;
            }
        }

        $safeDate = preg_replace('/[^0-9\-]+/', '', substr((string)$first['message_date'], 0, 10));
        $zipBaseName = 'photos-' . ($safeDate !== '' ? $safeDate : date('Y-m-d')) . '-' . date('His') . '.zip';
        $zipPath = tempnam(sys_get_temp_dir(), 'kinderlink_zip_');
        if ($zipPath === false) {
            http_response_code(500);
            echo 'Could not create ZIP file.';
            return;
        }

        $base = $this->photoStorageDir();
        $rowsByToken = [];
        foreach ($rows as $row) {
            $rowsByToken[(string)$row['access_token']] = $row;
        }

        $entries = [];
        $usedNames = [];
        foreach ($tokens as $token) {
            if (!isset($rowsByToken[$token])) {
                continue;
            }

            $row = $rowsByToken[$token];
            $path = $base . DIRECTORY_SEPARATOR . $row['stored_name'];
            if (!is_file($path)) {
                continue;
            }

            $entryName = preg_replace('~[\r\n\\/\x00-\x1F\x7F]+~', '_', (string)$row['original_name']);
            if ($entryName === '') {
                $entryName = 'photo';
            }

            if (isset($usedNames[$entryName])) {
                $ext = pathinfo($entryName, PATHINFO_EXTENSION);
                $baseName = pathinfo($entryName, PATHINFO_FILENAME);
                $suffix = 2;
                do {
                    $candidateName = $baseName . '-' . $suffix . ($ext !== '' ? '.' . $ext : '');
                    $suffix++;
                } while (isset($usedNames[$candidateName]));
                $entryName = $candidateName;
            }
            $usedNames[$entryName] = true;

            $entries[] = [
                'path' => $path,
                'name' => $entryName,
                'mtime' => (int)@filemtime($path),
            ];
        }

        if (empty($entries)) {
            @unlink($zipPath);
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $readmeTempPath = tempnam(sys_get_temp_dir(), 'kinderlink_zip_readme_');
        if ($readmeTempPath !== false) {
            $childLabel = 'Child ID: ' . (int)$first['child_id'];
            $groupLabel = 'Group ID: ' . (int)$first['group_id'];

            try {
                $metaStmt = $this->db->prepare(
                    'SELECT c.first_name, c.last_name, g.name AS group_name
                     FROM children c
                     LEFT JOIN `groups` g ON g.id = ?
                     WHERE c.id = ?
                     LIMIT 1'
                );
                $metaStmt->execute([(int)$first['group_id'], (int)$first['child_id']]);
                $meta = $metaStmt->fetch();
                if ($meta) {
                    $childName = trim((string)($meta['last_name'] ?? '') . ' ' . (string)($meta['first_name'] ?? ''));
                    $groupName = trim((string)($meta['group_name'] ?? ''));
                    if ($childName !== '') {
                        $childLabel = 'Child: ' . $childName;
                    }
                    if ($groupName !== '') {
                        $groupLabel = 'Group: ' . $groupName;
                    }
                }
            } catch (Throwable $e) {
                // Keep ID fallback when metadata lookup fails.
            }

            $readmeLines = [];
            $readmeLines[] = 'KinderLink - Photos ZIP';
            $readmeLines[] = 'Date: ' . substr((string)$first['message_date'], 0, 10);
            $readmeLines[] = $childLabel;
            $readmeLines[] = $groupLabel;
            $readmeLines[] = 'Files: ' . count($entries);
            $readmeLines[] = '';
            $readmeLines[] = 'Included files:';
            foreach ($entries as $entry) {
                $readmeLines[] = '- ' . (string)$entry['name'];
            }

            $written = @file_put_contents($readmeTempPath, implode("\r\n", $readmeLines) . "\r\n");
            if ($written !== false) {
                $entries[] = [
                    'path' => $readmeTempPath,
                    'name' => 'README.txt',
                    'mtime' => time(),
                ];
            } else {
                @unlink($readmeTempPath);
            }
        }

        if (!$this->createZipFile($entries, $zipPath)) {
            if (!empty($readmeTempPath) && is_file($readmeTempPath)) {
                @unlink($readmeTempPath);
            }
            @unlink($zipPath);
            http_response_code(500);
            echo 'Could not create ZIP file.';
            return;
        }

        if (!is_file($zipPath) || filesize($zipPath) === 0) {
            if (!empty($readmeTempPath) && is_file($readmeTempPath)) {
                @unlink($readmeTempPath);
            }
            @unlink($zipPath);
            http_response_code(500);
            echo 'Could not create ZIP file.';
            return;
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        @ini_set('zlib.output_compression', '0');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        // Remove all previously set headers (CSP, X-Frame-Options, etc.) so they
        // do not interfere with binary content delivery on shared hosting servers.
        header_remove();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipBaseName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        readfile($zipPath);
        if (!empty($readmeTempPath) && is_file($readmeTempPath)) {
            @unlink($readmeTempPath);
        }
        @unlink($zipPath);
        exit;
    }

    private function createZipFile(array $entries, string $zipPath): bool {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return false;
            }

            $added = 0;
            foreach ($entries as $entry) {
                $path = (string)($entry['path'] ?? '');
                $name = (string)($entry['name'] ?? '');
                if ($path === '' || $name === '' || !is_file($path)) {
                    continue;
                }
                if ($zip->addFile($path, $name)) {
                    $added++;
                }
            }

            $zip->close();
            return $added > 0;
        }

        return $this->createZipFileStoreOnly($entries, $zipPath);
    }

    private function createZipFileStoreOnly(array $entries, string $zipPath): bool {
        $out = @fopen($zipPath, 'wb');
        if (!$out) {
            return false;
        }

        $centralDir = '';
        $offset = 0;
        $count = 0;

        foreach ($entries as $entry) {
            $path = (string)($entry['path'] ?? '');
            $name = str_replace('\\', '/', (string)($entry['name'] ?? ''));
            if ($path === '' || $name === '' || !is_file($path)) {
                continue;
            }

            $data = @file_get_contents($path);
            if ($data === false) {
                continue;
            }

            $size = strlen($data);
            $crc = crc32($data);
            if ($crc < 0) {
                $crc += 4294967296;
            }

            $mtime = (int)($entry['mtime'] ?? 0);
            $dosDateTime = $this->zipDosDateTime32($mtime > 0 ? $mtime : time());
            $nameLen = strlen($name);

            $localHeader = "\x50\x4b\x03\x04"
                . "\x14\x00"
                . "\x00\x00"
                . "\x00\x00"
                . pack('V', $dosDateTime)
                . pack('V', (int)$crc)
                . pack('V', (int)$size)
                . pack('V', (int)$size)
                . pack('v', $nameLen)
                . pack('v', 0)
                . $name;

            if (@fwrite($out, $localHeader) === false) {
                @fclose($out);
                return false;
            }

            if ($size > 0 && @fwrite($out, $data) === false) {
                @fclose($out);
                return false;
            }

            $centralHeader = "\x50\x4b\x01\x02"
                . "\x00\x00"
                . "\x14\x00"
                . "\x00\x00"
                . "\x00\x00"
                . pack('V', $dosDateTime)
                . pack('V', (int)$crc)
                . pack('V', (int)$size)
                . pack('V', (int)$size)
                . pack('v', $nameLen)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', 0)
                . pack('V', (int)$offset)
                . $name;

            $centralDir .= $centralHeader;
            $offset += strlen($localHeader) + $size;
            $count++;
        }

        if ($count === 0) {
            @fclose($out);
            return false;
        }

        if (@fwrite($out, $centralDir) === false) {
            @fclose($out);
            return false;
        }

        $eocd = "\x50\x4b\x05\x06"
            . "\x00\x00"
            . "\x00\x00"
            . pack('v', $count)
            . pack('v', $count)
            . pack('V', strlen($centralDir))
            . pack('V', (int)$offset)
            . "\x00\x00";
        if (@fwrite($out, $eocd) === false) {
            @fclose($out);
            return false;
        }

        @fclose($out);
        return true;
    }

    private function zipDosDateTime32(int $timestamp): int {
        $timestamp = max($timestamp, mktime(0, 0, 0, 1, 1, 1980));
        $dt = getdate($timestamp);

        $dosTime = (($dt['hours'] & 0x1F) << 11) | (($dt['minutes'] & 0x3F) << 5) | (int)floor(($dt['seconds'] & 0x3E) / 2);
        $dosDate = ((($dt['year'] - 1980) & 0x7F) << 9) | (($dt['mon'] & 0x0F) << 5) | ($dt['mday'] & 0x1F);

        return (($dosDate & 0xFFFF) << 16) | ($dosTime & 0xFFFF);
    }

    private function photoStorageDir(): string {
        $dir = ROOT . '/storage/message_photos';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function childInGroup(int $childId, int $groupId): bool {
        $stmt = $this->db->prepare('SELECT 1 FROM children_groups WHERE child_id = ? AND group_id = ? LIMIT 1');
        $stmt->execute([$childId, $groupId]);
        return (bool)$stmt->fetchColumn();
    }

    private function canAccessGroup(int $groupId): bool {
        if ($groupId <= 0) {
            return false;
        }
        if (Auth::isAdmin()) {
            return true;
        }
        if (!Auth::isTeacher()) {
            return false;
        }

        $userId = (int)(Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT 1 FROM teacher_groups WHERE user_id = ? AND group_id = ? LIMIT 1');
        $stmt->execute([$userId, $groupId]);
        return (bool)$stmt->fetchColumn();
    }

    private function getAccessiblePhotoById(int $photoId): ?array {
        if ($photoId <= 0) {
            return null;
        }

        if (Auth::isAdmin()) {
            $stmt = $this->db->prepare('SELECT id, group_id, stored_name, hidden_at FROM message_photos WHERE id = ? LIMIT 1');
            $stmt->execute([$photoId]);
            $row = $stmt->fetch();
            return $row ?: null;
        }

        if (!Auth::isTeacher()) {
            return null;
        }

        $userId = (int)(Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT p.id, p.group_id, p.stored_name, p.hidden_at
             FROM message_photos p
             JOIN teacher_groups tg ON tg.group_id = p.group_id
             WHERE p.id = ? AND tg.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$photoId, $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function filterAccessiblePhotoIds(array $ids): array {
        $ids = $this->normalizedPhotoIds($ids);
        if (empty($ids)) {
            return [];
        }

        if (Auth::isAdmin()) {
            return $ids;
        }

        if (!Auth::isTeacher()) {
            return [];
        }

        $userId = (int)(Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$userId]);
        $stmt = $this->db->prepare(
            'SELECT DISTINCT p.id
             FROM message_photos p
             JOIN teacher_groups tg ON tg.group_id = p.group_id
             WHERE p.id IN (' . $placeholders . ') AND tg.user_id = ?'
        );
        $stmt->execute($params);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    private function normalizeFilesArray(array $files): array {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
        return $normalized;
    }

    private function getMessagePhotos(int $childId, int $groupId, string $messageDate): array {
        $date = substr($messageDate, 0, 10);
        $stmt = $this->db->prepare(
            'SELECT original_name, access_token
             FROM message_photos
             WHERE child_id = ? AND group_id = ? AND message_date = ?
               AND hidden_at IS NULL
             ORDER BY id ASC'
        );
        $stmt->execute([$childId, $groupId, $date]);
        return $stmt->fetchAll();
    }

    private function photoPublicUrl(string $token): string {
        return $this->appAbsoluteUrl('/messages/photo?t=' . rawurlencode($token));
    }

    private function photoViewerUrl(string $token): string {
        return $this->appAbsoluteUrl('/messages/photo-view?t=' . rawurlencode($token));
    }

    private function photoZipUrl(array $tokens): string {
        $tokens = array_values(array_filter($tokens, function($token) {
            return is_string($token) && preg_match('/^[a-f0-9]{48}$/', $token);
        }));

        return $this->appAbsoluteUrl('/messages/photo-zip?t=' . rawurlencode(implode(',', $tokens)));
    }

    private function appAbsoluteUrl(string $path): string {
        $path = '/' . ltrim($path, '/');
        $basePath = rtrim(BASE_URL, '/');

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            ? 'https'
            : 'http';

        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            $configuredHost = (string)parse_url((string)APP_URL, PHP_URL_HOST);
            if ($configuredHost !== '') {
                $host = $configuredHost;
            }
        }

        if ($host === '') {
            return rtrim((string)APP_URL, '/') . $path;
        }

        return $scheme . '://' . $host . $basePath . $path;
    }

    private function buildPhotoZipHtml(array $photos, string $childName = '', string $messageDate = ''): string {
        if (empty($photos)) {
            return '';
        }

        $tokens = [];
        foreach ($photos as $photo) {
            $tokens[] = (string)($photo['access_token'] ?? '');
        }

        $downloadUrl = $this->e($this->photoZipUrl($tokens));
        $safeChildName = trim($childName) !== '' ? trim($childName) : 'το παιδί';
        $safeDate = substr(trim($messageDate), 0, 10);

        $html = '<div style="margin-top:16px;padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#fafafa;">';
        $html .= '<strong style="display:block;margin-bottom:8px;">Φωτογραφίες ημέρας</strong>';
        $html .= '<a href="' . $downloadUrl . '" target="_blank" rel="noopener" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:700;">Λήψη όλων ως ZIP</a>';
        $html .= '<div style="margin-top:8px;font-size:12px;color:#6b7280;">';
        $html .= $this->e($safeChildName);
        if ($safeDate !== '') {
            $html .= ' · ' . $this->e($safeDate);
        }
        $html .= '</div>';
        $html .= '<div style="margin-top:10px;font-size:12px;color:#374151;">Εναλλακτικά, μπορείτε να ανοίξετε μεμονωμένες φωτογραφίες:</div>';
        $html .= '<ul style="margin:6px 0 0 18px;padding:0;">';
        foreach ($photos as $photo) {
            $name = $this->e((string)($photo['original_name'] ?? 'photo'));
            $url = $this->e($this->photoViewerUrl((string)($photo['access_token'] ?? '')));
            $html .= '<li style="margin-bottom:4px;"><a href="' . $url . '" target="_blank" rel="noopener">' . $name . '</a></li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    private function cleanupOldPhotos(): void {
        $retentionDays = $this->getPhotoRetentionDays();
        $hiddenGraceDays = $this->getPhotoHiddenGraceDays();

        $stmt = $this->db->query(
            'SELECT id, stored_name FROM message_photos
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)
                OR (hidden_at IS NOT NULL AND hidden_at < DATE_SUB(NOW(), INTERVAL ' . $hiddenGraceDays . ' DAY))'
        );
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return;
        }

        $base = $this->photoStorageDir();
        foreach ($rows as $row) {
            $path = $base . DIRECTORY_SEPARATOR . $row['stored_name'];
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->db->exec(
            'DELETE FROM message_photos
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)
                OR (hidden_at IS NOT NULL AND hidden_at < DATE_SUB(NOW(), INTERVAL ' . $hiddenGraceDays . ' DAY))'
        );
    }

    private function getPhotoRetentionDays(): int {
        if ($this->photoRetentionDays !== null) {
            return $this->photoRetentionDays;
        }

        $this->photoRetentionDays = $this->getIntParameter(
            'photo_retention_days',
            self::PHOTO_RETENTION_DAYS_DEFAULT,
            self::PHOTO_RETENTION_DAYS_MIN,
            self::PHOTO_RETENTION_DAYS_MAX
        );

        return $this->photoRetentionDays;
    }

    private ?string $schoolName = null;

    private function getSchoolName(): string {
        if ($this->schoolName !== null) {
            return $this->schoolName;
        }
        try {
            $stmt = $this->db->prepare('SELECT param_value FROM parameters WHERE param_key = ? LIMIT 1');
            $stmt->execute(['school_name']);
            $val = trim((string)$stmt->fetchColumn());
            $this->schoolName = $val !== '' ? $val : '';
        } catch (Throwable $e) {
            $this->schoolName = '';
        }
        return $this->schoolName;
    }

    private function getPhotoHiddenGraceDays(): int {
        if ($this->photoHiddenGraceDays !== null) {
            return $this->photoHiddenGraceDays;
        }

        $this->photoHiddenGraceDays = $this->getIntParameter(
            'photo_hidden_grace_days',
            self::PHOTO_HIDDEN_GRACE_DAYS_DEFAULT,
            self::PHOTO_HIDDEN_GRACE_DAYS_MIN,
            self::PHOTO_HIDDEN_GRACE_DAYS_MAX
        );

        return $this->photoHiddenGraceDays;
    }

    private function getIntParameter(string $key, int $default, int $min, int $max): int {
        try {
            $stmt = $this->db->prepare('SELECT param_value FROM parameters WHERE param_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            if ($value === false || !is_numeric($value)) {
                return $default;
            }
            $num = (int)$value;
            if ($num < $min) {
                return $min;
            }
            if ($num > $max) {
                return $max;
            }
            return $num;
        } catch (Throwable $e) {
            return $default;
        }
    }

    private function photosTableExists(): bool {
        if ($this->hasPhotosTable !== null) {
            return $this->hasPhotosTable;
        }

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'message_photos'");
            $this->hasPhotosTable = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->hasPhotosTable = false;
        }

        return $this->hasPhotosTable;
    }

    private function buildEmailBody(string $template, array $replacements): string {
        // Existing templates include the unit after the placeholder. Remove it
        // only for no sleep, before inserting any child-entered content.
        if (($replacements['|*sleep*|'] ?? null) === 'Όχι') {
            $template = preg_replace_callback(
                '/(\|\*sleep\*\|)(?:[\s\x{00A0}]|&nbsp;|&#0*160;|&#x0*a0;)*((?:\p{L}|&(?:[a-z][a-z0-9]*|#[0-9]+|#x[0-9a-f]+);)+)/iu',
                static function (array $match): string {
                    // Rich-text editors may encode Greek letters individually.
                    // Decode only the candidate unit, never the template HTML.
                    $unit = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    return preg_match('/^λεπτά$/iuD', $unit) === 1 ? $match[1] : $match[0];
                },
                $template
            ) ?? $template;
        }
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function ratingColor(int $value): string {
        return match ($value) {
            1 => '#e74c3c',
            2 => '#f39c12',
            3 => '#3498db',
            4 => '#27ae60',
            default => '#6b7280',
        };
    }

    private function ratingBadge(string $label, string $color): string {
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeColor = htmlspecialchars($color, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<span style="display:inline-block;min-width:110px;text-align:center;padding:6px 10px;border-radius:999px;color:#fff;background:'
            . $safeColor
            . ';font-weight:700;">'
            . $safeLabel
            . '</span>';
    }

    private function sendEmail(array $to, string $subject, string $body): bool {
        $this->lastMailError = '';
        $addresses = array_values(array_unique(array_filter(array_map([$this, 'sanitizeEmail'], $to))));

        if (empty($addresses)) {
            $this->lastMailError = 'Δεν υπάρχουν έγκυρες διευθύνσεις παραληπτών.';
            return false;
        }

        $lastError = '';
        foreach ($addresses as $address) {
            $ok = $this->sendSingleEmail($address, $subject, $body);
            if (!$ok) {
                $lastError = $this->lastMailError !== '' ? $this->lastMailError : 'Αποτυχία SMTP αποστολής.';
            }
        }

        if ($lastError !== '') {
            $this->lastMailError = $lastError;
            return false;
        }

        return true;
    }

    private function sendSingleEmail(string $to, string $subject, string $body): bool {
        $provider = defined('EMAIL_PROVIDER') ? strtolower((string)EMAIL_PROVIDER) : 'smtp';
        if ($provider === 'brevo') {
            return $this->sendViaBrevo($to, $subject, $body);
        }
        return $this->sendViaSmtp($to, $subject, $body);
    }

    private function sendViaSmtp(string $to, string $subject, string $body): bool {
        $host   = trim((string)(defined('SMTP_HOST') ? SMTP_HOST : 'localhost'));
        $port   = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $user   = trim((string)(defined('SMTP_USER') ? SMTP_USER : ''));
        $pass   = (string)(defined('SMTP_PASS') ? SMTP_PASS : '');
        $secure = strtolower((string)(defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'));
        $from   = trim((string)(defined('SMTP_FROM') ? SMTP_FROM : $user));
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME_GR;
        $schoolName = $this->getSchoolName();
        if ($schoolName !== '') {
            $fromName = $schoolName;
        }
        $heloHost = $host;
        if (strpos($from, '@') !== false) {
            $parts = explode('@', $from);
            $domain = trim((string)end($parts));
            if ($domain !== '') $heloHost = $domain;
        }

        $this->lastMailError = '';

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
            error_clear_last();
            $cryptoOk = @stream_socket_enable_crypto($socket, true, $this->smtpTlsCryptoMethod());
            if (!$cryptoOk) {
                $err = error_get_last();
                $detail = ($err && !empty($err['message']))
                    ? preg_replace('/\s+/', ' ', trim((string)$err['message']))
                    : 'άγνωστο (openssl)';
                $this->lastMailError = 'Αποτυχία TLS handshake με SMTP server: ' . $detail;
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

        $recipient = $this->sanitizeEmail($to);
        if ($recipient === '') {
            $this->lastMailError = 'Δεν υπάρχουν έγκυρες διευθύνσεις παραληπτών.';
            fclose($socket);
            return false;
        }

        if (!$this->smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251])) {
            fclose($socket);
            return false;
        }

        if (!$this->smtpCommand($socket, 'DATA', [354])) {
            fclose($socket);
            return false;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $boundary = 'b1_' . bin2hex(random_bytes(12));
        $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)));
        if ($textBody === '') $textBody = ' ';
        $qpTextBody = quoted_printable_encode($textBody);
        $qpHtmlBody = quoted_printable_encode($body);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $encodedFromName . ' <' . $from . '>',
            'To: ' . $recipient,
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

    private function smtpTlsCryptoMethod(): int {
        // Ξεκινάμε από το γενικό TLS client flag· σε παλιότερες PHP αυτό σημαίνει μόνο TLS 1.0,
        // οπότε προσθέτουμε ρητά και τα νεότερα protocols (Gmail/Outlook απαιτούν 1.2+).
        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        return $method;
    }

    private function smtpSocketContext(string $host) {
        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $host,
            'SNI_enabled' => true,
            'crypto_method' => $this->smtpTlsCryptoMethod(),
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

    private function sendViaBrevo(string $to, string $subject, string $body): bool {
        $apiKey = defined('BREVO_API_KEY') ? trim((string)BREVO_API_KEY) : '';
        $endpoint = defined('BREVO_API_ENDPOINT')
            ? trim((string)BREVO_API_ENDPOINT)
            : 'https://api.brevo.com/v3/smtp/email';

        if ($apiKey === '') {
            $this->lastMailError = 'Λείπει το BREVO_API_KEY στο environment του server.';
            return false;
        }
        if (!function_exists('curl_init')) {
            $this->lastMailError = 'Δεν είναι διαθέσιμη η επέκταση cURL της PHP για κλήση του Brevo API.';
            return false;
        }

        $recipient = $this->sanitizeEmail($to);
        if ($recipient === '' || strpos($recipient, '@') === false) {
            $this->lastMailError = 'Δεν υπάρχουν έγκυρες διευθύνσεις παραληπτών.';
            return false;
        }

        $from = trim((string)(defined('SMTP_FROM') ? SMTP_FROM : ''));
        if ($from === '' || strpos($from, '@') === false) {
            $this->lastMailError = 'Λείπει το SMTP_FROM (διεύθυνση αποστολέα) στο environment.';
            return false;
        }
        $fromName = defined('SMTP_FROM_NAME') ? (string)SMTP_FROM_NAME : APP_NAME_GR;
        $schoolName = $this->getSchoolName();
        if ($schoolName !== '') {
            $fromName = $schoolName;
        }

        $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)));
        if ($textBody === '') {
            $textBody = ' ';
        }

        $payload = [
            'sender' => ['name' => $fromName, 'email' => $from],
            'to' => [['email' => $recipient]],
            'replyTo' => ['email' => $from],
            'subject' => $subject,
            'htmlContent' => $body,
            'textContent' => $textBody,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->lastMailError = 'Αποτυχία σειριοποίησης JSON για Brevo: ' . json_last_error_msg();
            return false;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'api-key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->lastMailError = 'Αποτυχία κλήσης Brevo API: ' . ($curlErr !== '' ? $curlErr : 'άγνωστο cURL error');
            return false;
        }

        if ($status >= 200 && $status < 300) {
            return true;
        }

        $decoded = json_decode((string)$response, true);
        $apiMessage = '';
        if (is_array($decoded)) {
            if (!empty($decoded['message']) && is_string($decoded['message'])) {
                $apiMessage = $decoded['message'];
            } elseif (!empty($decoded['code']) && is_string($decoded['code'])) {
                $apiMessage = $decoded['code'];
            }
        }
        if ($apiMessage === '') {
            $apiMessage = 'HTTP ' . $status;
        }

        $this->lastMailError = 'Αποτυχία Brevo API (HTTP ' . $status . '): ' . $apiMessage;
        return false;
    }

    // ── Message List ──────────────────────────────────────────────────

    public function list(): void {
        Auth::requireRole('admin', 'teacher');

        $user = Auth::user();
        if (Auth::isAdmin()) {
            $groups = $this->db->query('SELECT id, name FROM `groups` WHERE is_current=1 ORDER BY name')->fetchAll();
        } else {
            $stmt = $this->db->prepare(
                'SELECT g.id, g.name FROM `groups` g
                 JOIN teacher_groups tg ON tg.group_id=g.id
                 WHERE tg.user_id=? AND g.is_current=1 ORDER BY g.name'
            );
            $stmt->execute([$user['id']]);
            $groups = $stmt->fetchAll();
        }

        $this->render('messages/list', [
            'pageTitle' => 'Λίστα Μηνυμάτων',
            'groups'    => $groups,
        ]);
    }

    public function apiSearch(): void {
        Auth::requireRole('admin', 'teacher');

        $groupId = (int)($_POST['group_id'] ?? 0);
        $from    = $_POST['from'] ?? date('Y-m-d');
        $to      = $_POST['to']   ?? date('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

        $where  = 'WHERE DATE(m.message_date) BETWEEN ? AND ?';
        $params = [$from, $to];

        if (Auth::isTeacher()) {
            $where .= ' AND EXISTS (SELECT 1 FROM teacher_groups tg WHERE tg.group_id = m.group_id AND tg.user_id = ?)';
            $params[] = (int)(Auth::user()['id'] ?? 0);
        }

        if ($groupId > 0) {
            if (!$this->canAccessGroup($groupId)) {
                $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
                return;
            }
            $where   .= ' AND m.group_id = ?';
            $params[] = $groupId;
        }

        $stmt = $this->db->prepare(
            "SELECT m.id, DATE(m.message_date) AS msg_date,
                    c.first_name, c.last_name, g.name AS group_name,
                    m.breakfast, m.lunch, m.mood, m.sleep_minutes,
                    m.wc, m.activities, m.comments, m.email_status
             FROM messages m
             JOIN children c ON c.id = m.child_id
             JOIN `groups`   g ON g.id = m.group_id
             $where
             ORDER BY m.message_date DESC, c.last_name, c.first_name
             LIMIT 200"
        );
        $stmt->execute($params);

        $this->json(['rows' => $stmt->fetchAll()]);
    }

    public function apiDelete(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->json(['error' => 'Μη έγκυρο ID.'], 422); return; }
        $this->db->prepare('DELETE FROM messages WHERE id=?')->execute([$id]);
        $this->json(['success' => true]);
    }

    public function apiDeleteBulk(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $idsString = trim($_POST['ids'] ?? '');
        if (empty($idsString)) { 
            $this->json(['error' => 'Μη έγκυρα IDs.'], 422); 
            return; 
        }

        // Parse comma-separated IDs
        $idArray = array_map('intval', array_filter(array_map('trim', explode(',', $idsString))));
        
        if (empty($idArray)) {
            $this->json(['error' => 'Μη έγκυρα IDs.'], 422);
            return;
        }

        // Delete in batch
        $placeholders = implode(',', array_fill(0, count($idArray), '?'));
        $stmt = $this->db->prepare("DELETE FROM messages WHERE id IN ($placeholders)");
        $stmt->execute($idArray);
        
        $deleted = $stmt->rowCount();
        $this->json(['success' => true, 'deleted' => (int)$deleted]);
    }

    // ── Free Email ────────────────────────────────────────────────────

    public function freeEmail(): void {
        Auth::requireRole('admin', 'teacher');

        $user = Auth::user();
        if (Auth::isAdmin()) {
            $groups = $this->db->query('SELECT id, name FROM `groups` WHERE is_current=1 ORDER BY name')->fetchAll();
        } else {
            $stmt = $this->db->prepare(
                'SELECT g.id, g.name FROM `groups` g
                 JOIN teacher_groups tg ON tg.group_id=g.id
                 WHERE tg.user_id=? AND g.is_current=1 ORDER BY g.name'
            );
            $stmt->execute([$user['id']]);
            $groups = $stmt->fetchAll();
        }

        $this->render('messages/free-email', [
            'pageTitle' => 'Ελεύθερο Email',
            'groups'    => $groups,
        ]);
    }

    public function apiSendFreeEmail(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        $groupId  = (int)($_POST['group_id'] ?? 0);
        $subject  = trim($_POST['subject']   ?? '');
        $body     = $_POST['body']           ?? '';
        $sendAll  = isset($_POST['send_all']);

        if ($subject === '') { $this->json(['error' => 'Το θέμα είναι υποχρεωτικό.'], 422); return; }
        if ($body === '')    { $this->json(['error' => 'Το κείμενο είναι υποχρεωτικό.'], 422); return; }

        $mode = $this->db->query("SELECT param_value FROM parameters WHERE param_key='email_mode'")->fetchColumn() ?: 'virtual';

        // Collect email addresses
        if ($sendAll) {
            if (!Auth::isAdmin()) {
                $this->json(['error' => 'Μόνο ο διαχειριστής μπορεί να στείλει σε όλους.'], 403);
                return;
            }
            $stmt = $this->db->query(
                'SELECT email1, email2, send_email1, send_email2
                 FROM children
                 WHERE active=1 AND (email1 <> \'\' OR email2 <> \'\')'
            );
        } elseif ($groupId > 0) {
            if (!$this->canAccessGroup($groupId)) {
                $this->json(['error' => 'Δεν έχετε πρόσβαση στο επιλεγμένο τμήμα.'], 403);
                return;
            }
            $stmt = $this->db->prepare(
                'SELECT c.email1, c.email2, c.send_email1, c.send_email2
                 FROM children c JOIN children_groups cg ON cg.child_id=c.id
                 WHERE cg.group_id=? AND c.active=1'
            );
            $stmt->execute([$groupId]);
        } else {
            $this->json(['error' => 'Επιλέξτε τμήμα ή \'Όλοι\'.'], 422); return;
        }

        $children  = $stmt->fetchAll();
        $addresses = [];
        foreach ($children as $c) {
            if ($c['send_email1'] && $c['email1']) $addresses[] = $c['email1'];
            if ($c['send_email2'] && $c['email2']) $addresses[] = $c['email2'];
        }
        $addresses = array_unique($addresses);

        $errorDetail = '';
        if ($mode === 'real' && !empty($addresses)) {
            $sentCount = 0;
            $failedCount = 0;
            foreach ($addresses as $addr) {
                $ok = $this->sendEmail([$addr], $subject, $body);
                if ($ok) {
                    $sentCount++;
                } else {
                    $failedCount++;
                    if ($errorDetail === '') $errorDetail = $this->lastMailError !== '' ? $this->lastMailError : 'Αποτυχία SMTP αποστολής.';
                }
            }
            $result = $failedCount === 0;
        } elseif ($mode === 'real' && empty($addresses)) {
            $result = false;
            $errorDetail = 'Δεν βρέθηκαν ενεργοί παραλήπτες (send_email1/send_email2).';
        } else {
            $result = true;
        }

        // Log free email
        $this->db->prepare(
            'INSERT INTO free_emails (group_id, subject, body, sent_at, email_status)
             VALUES (?,?,?,NOW(),?)'
        )->execute([$groupId ?: null, $subject, $body, $mode === 'real' ? ($result ? 'sent' : 'failed') : 'virtual']);

        $this->json([
            'success' => true,
            'mode' => $mode,
            'count' => count($addresses),
            'error_detail' => $errorDetail,
            'result' => $result,
        ]);
    }

    private function normalizedPhotoIds(mixed $idsRaw): array {
        $ids = [];
        if (!is_array($idsRaw)) {
            return [];
        }
        foreach ($idsRaw as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    private function formatBytes(int $bytes): string {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2) . ' MB';
        }
        return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}
