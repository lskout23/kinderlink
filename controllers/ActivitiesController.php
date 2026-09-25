<?php
class ActivitiesController extends Controller {

    public function index(): void {
        Auth::requireRole('admin', 'teacher');
        $this->render('activities/index', ['pageTitle' => 'Ορισμός Δραστηριοτήτων, Παρατηρήσεων']);
    }

    public function apiList(): void {
        Auth::requireRole('admin', 'teacher');

        $type     = $_POST['type'] ?? 'both'; // 'both' or 'email_only'
        $page     = max(1, (int)($_POST['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($_POST['page_size'] ?? 20)));
        $offset   = ($page - 1) * $pageSize;

        if ($type === 'email_only') {
            $where = "type = 'email_only'";
        } else {
            $where = "type = 'both'";
        }

        $total = $this->db->query("SELECT COUNT(*) FROM activities WHERE $where")->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT id, name, type FROM activities WHERE $where ORDER BY sort_order, name LIMIT ? OFFSET ?"
        );
        $stmt->execute([$pageSize, $offset]);
        $rows = $stmt->fetchAll();

        $this->json(['total' => (int)$total, 'rows' => $rows]);
    }

    public function apiSave(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['both', 'email_only']) ? $_POST['type'] : 'both';

        if ($name === '') {
            $this->json(['error' => 'Το όνομα δραστηριότητας είναι υποχρεωτικό.'], 422);
            return;
        }

        if ($id > 0) {
            $this->db->prepare('UPDATE activities SET name=?, type=? WHERE id=?')
                     ->execute([$name, $type, $id]);
            $this->json(['success' => true, 'id' => $id]);
        } else {
            $maxOrder = $this->db->query("SELECT COALESCE(MAX(sort_order),0) FROM activities WHERE type='$type'")->fetchColumn();
            $this->db->prepare('INSERT INTO activities (name, type, sort_order) VALUES (?,?,?)')
                     ->execute([$name, $type, $maxOrder + 1]);
            $this->json(['success' => true, 'id' => $this->db->lastInsertId()]);
        }
    }

    public function apiDelete(): void {
        Auth::requireRole('admin', 'teacher');
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->json(['error' => 'Μη έγκυρο ID.'], 422); return; }

        $this->db->prepare('DELETE FROM activities WHERE id=?')->execute([$id]);
        $this->json(['success' => true]);
    }
}
