<?php
class ParentController extends Controller {

    public function index(): void {
        Auth::requireRole('parent');

        $user = Auth::user();

        // Find children linked to this parent account
        $stmt = $this->db->prepare(
            'SELECT id, first_name, last_name FROM children WHERE parent_user_id = ? AND active = 1 ORDER BY last_name, first_name'
        );
        $stmt->execute([$user['id']]);
        $children = $stmt->fetchAll();

        // If no children linked, try matching by email
        if (empty($children)) {
            $stmtByEmail = $this->db->prepare(
                'SELECT id, first_name, last_name FROM children
                 WHERE (email1 = ? OR email2 = ?) AND active = 1
                 ORDER BY last_name, first_name'
            );
            $stmtByEmail->execute([$user['email'], $user['email']]);
            $children = $stmtByEmail->fetchAll();
        }

        // Check if there are messages today for any linked child.
        $todayCount = 0;
        if (!empty($children)) {
            $ids = array_column($children, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $todayStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM messages WHERE child_id IN (' . $placeholders . ') AND DATE(message_date) = CURDATE()'
            );
            $todayStmt->execute($ids);
            $todayCount = (int)$todayStmt->fetchColumn();
        }

        $this->render('parent/dashboard', [
            'pageTitle'   => 'Χαρτοφυλάκιο Παιδιού',
            'user'        => $user,
            'children'    => $children,
            'todayCount'  => $todayCount,
        ]);
    }

    public function apiMessages(): void {
        Auth::requireRole('parent');

        $user    = Auth::user();
        $childId = (int)($_POST['child_id'] ?? 0);
        $from    = $_POST['from'] ?? date('Y-m-01');
        $to      = $_POST['to']   ?? date('Y-m-d');

        if ($childId <= 0) { $this->json(['error' => 'Μη έγκυρο παιδί.'], 422); return; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

        // Verify this child belongs to the parent
        $stmt = $this->db->prepare(
            'SELECT id FROM children WHERE id = ? AND parent_user_id = ? AND active = 1'
        );
        $stmt->execute([$childId, $user['id']]);
        if (!$stmt->fetch()) {
            // Try email fallback
            $stmt2 = $this->db->prepare(
                'SELECT id FROM children WHERE id = ? AND (email1 = ? OR email2 = ?) AND active = 1'
            );
            $stmt2->execute([$childId, $user['email'], $user['email']]);
            if (!$stmt2->fetch()) {
                $this->json(['error' => 'Δεν επιτρέπεται η πρόσβαση.'], 403);
                return;
            }
        }

        $stmt = $this->db->prepare(
            'SELECT m.*, g.name AS group_name FROM messages m
             JOIN `groups` g ON g.id = m.group_id
             WHERE m.child_id = ? AND DATE(m.message_date) BETWEEN ? AND ?
             ORDER BY m.message_date DESC LIMIT 100'
        );
        $stmt->execute([$childId, $from, $to]);
        $rows = $stmt->fetchAll();

        // Find last available message date for empty-state hint.
        $lastDate = null;
        if (empty($rows)) {
            $lastStmt = $this->db->prepare(
                'SELECT DATE(message_date) FROM messages WHERE child_id = ? ORDER BY message_date DESC LIMIT 1'
            );
            $lastStmt->execute([$childId]);
            $lastDate = $lastStmt->fetchColumn() ?: null;
        }

        $this->json(['rows' => $rows, 'last_message_date' => $lastDate]);
    }
}
