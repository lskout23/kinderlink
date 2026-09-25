<?php
class DashboardController extends Controller {

    public function index(): void {
        Auth::requireRole('admin', 'teacher');

        $user = Auth::user();

        // Stats for admin
        $stats = [];
        if ($user['role'] === 'admin') {
            $today = date('Y-m-d');

            $stats['children'] = $this->db->query('SELECT COUNT(*) FROM children WHERE active = 1')->fetchColumn();
            $stats['groups']   = $this->db->query('SELECT COUNT(*) FROM `groups` WHERE is_current = 1')->fetchColumn();

            // (1) Καταχωρήσεις σήμερα — όλες οι γραμμές messages για τη σημερινή ημέρα
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM messages WHERE message_date = ?');
            $stmt->execute([$today]);
            $stats['messages_today'] = (int)$stmt->fetchColumn();

            // (2) Emails εστάλησαν σήμερα — άθροισμα ενεργών παραληπτών (email1+email2)
            //     για τα παιδιά που το email στάλθηκε επιτυχώς
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(
                    (CASE WHEN c.send_email1 = 1 AND c.email1 IS NOT NULL AND c.email1 <> '' THEN 1 ELSE 0 END) +
                    (CASE WHEN c.send_email2 = 1 AND c.email2 IS NOT NULL AND c.email2 <> '' THEN 1 ELSE 0 END)
                 ), 0)
                 FROM messages m
                 JOIN children c ON c.id = m.child_id
                 WHERE m.message_date = ? AND m.email_status IN ('sent', 'virtual')"
            );
            $stmt->execute([$today]);
            $stats['emails_sent_today'] = (int)$stmt->fetchColumn();

            // (3) Απόντες / δεν εστάλη — μηνύματα αποθηκευμένα αλλά χωρίς επιτυχή αποστολή
            //     (περιλαμβάνει status='pending' & 'failed')
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM messages
                 WHERE message_date = ?
                   AND email_status IN ('pending', 'failed')"
            );
            $stmt->execute([$today]);
            $stats['absent_today'] = (int)$stmt->fetchColumn();
            // Include persisted absences even without a saved message, without
            // counting an absent child's pending message a second time.
            try {
                if ((new Attendance($this->db))->isReady()) {
                    $stmt = $this->db->prepare("SELECT COUNT(*) FROM (
                        SELECT child_id FROM messages WHERE message_date = ? AND email_status IN ('pending', 'failed')
                        UNION
                        SELECT a.child_id FROM child_attendance a JOIN children c ON c.id = a.child_id
                        WHERE a.attendance_date = ? AND a.is_absent = 1 AND c.active = 1
                    ) AS absent_or_pending");
                    $stmt->execute([$today, $today]);
                    $stats['absent_today'] = (int)$stmt->fetchColumn();
                }
            } catch (Throwable $e) {
                // Keep the existing pending count if attendance is unavailable.
            }
        }

        $this->render('dashboard/index', [
            'pageTitle' => 'Κέντρο Ελέγχου',
            'stats'     => $stats,
        ]);
    }
}
