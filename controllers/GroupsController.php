<?php
class GroupsController extends Controller {

    public function index(): void {
        Auth::requireRole('admin');
        $this->render('groups/index', ['pageTitle' => 'Ορισμός Τμημάτων']);
    }

    public function childrenPerGroup(): void {
        Auth::requireRole('admin');

        // Load groups with their children
        $groups = $this->db->query(
            'SELECT g.id, g.name, g.is_current,
                    COUNT(cg.child_id) AS child_count
             FROM `groups` g
             LEFT JOIN children_groups cg ON cg.group_id = g.id
             GROUP BY g.id ORDER BY g.is_current DESC, g.name'
        )->fetchAll();

        foreach ($groups as &$g) {
            $stmt = $this->db->prepare(
                'SELECT c.id, c.first_name, c.last_name FROM children c
                 JOIN children_groups cg ON cg.child_id = c.id
                 WHERE cg.group_id = ? ORDER BY c.last_name, c.first_name'
            );
            $stmt->execute([$g['id']]);
            $g['children'] = $stmt->fetchAll();
        }

        // All active children for re-assignment
        $allChildren = $this->db->query(
            'SELECT id, first_name, last_name FROM children WHERE active=1 ORDER BY last_name, first_name'
        )->fetchAll();

        $this->render('groups/children-per-group', [
            'pageTitle'   => 'Παιδιά ανά Τμήμα',
            'groups'      => $groups,
            'allChildren' => $allChildren,
        ]);
    }

    // ----------------------------------------------------------------
    // API
    // ----------------------------------------------------------------

    public function apiList(): void {
        Auth::requireRole('admin');

        $page     = max(1, (int)($_POST['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($_POST['page_size'] ?? 20)));
        $offset   = ($page - 1) * $pageSize;

        $total = $this->db->query('SELECT COUNT(*) FROM `groups`')->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT id, name, is_current FROM `groups` ORDER BY is_current DESC, name LIMIT ? OFFSET ?'
        );
        $stmt->execute([$pageSize, $offset]);
        $rows = $stmt->fetchAll();

        $this->json(['total' => (int)$total, 'rows' => $rows]);
    }

    public function apiSave(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;

        if ($name === '') {
            $this->json(['error' => 'Το όνομα τμήματος είναι υποχρεωτικό.'], 422);
            return;
        }

        if ($id > 0) {
            $this->db->prepare('UPDATE `groups` SET name=?, is_current=? WHERE id=?')
                     ->execute([$name, $isCurrent, $id]);
            $this->json(['success' => true, 'id' => $id]);
        } else {
            $this->db->prepare('INSERT INTO `groups` (name, is_current) VALUES (?,?)')
                     ->execute([$name, $isCurrent]);
            $this->json(['success' => true, 'id' => $this->db->lastInsertId()]);
        }
    }

    public function apiDelete(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->json(['error' => 'Μη έγκυρο ID.'], 422); return; }

        $this->db->prepare('DELETE FROM `groups` WHERE id=?')->execute([$id]);
        $this->json(['success' => true]);
    }

    /** Assign children to a group */
    public function apiAssignChildren(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $groupId    = (int)($_POST['group_id'] ?? 0);
        $childIds   = $_POST['child_ids'] ?? [];
        if (!is_array($childIds)) $childIds = [];
        $childIds   = array_map('intval', $childIds);

        if ($groupId <= 0) { $this->json(['error' => 'Μη έγκυρο τμήμα.'], 422); return; }

        // Replace assignments
        $this->db->prepare('DELETE FROM children_groups WHERE group_id=?')->execute([$groupId]);
        foreach ($childIds as $cid) {
            if ($cid <= 0) continue;
            $this->db->prepare('INSERT IGNORE INTO children_groups (child_id, group_id) VALUES (?,?)')
                     ->execute([$cid, $groupId]);
        }

        $this->json(['success' => true]);
    }
}
