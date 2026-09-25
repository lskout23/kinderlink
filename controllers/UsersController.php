<?php
class UsersController extends Controller {

    private function usernamePolicyText(): string {
        return 'Περιορισμοί username: 4-50 χαρακτήρες, μόνο λατινικά γράμματα, αριθμοί, @, τελεία (.), κάτω παύλα (_) ή παύλα (-).';
    }

    private function passwordPolicyText(): string {
        return 'Περιορισμοί κωδικού: τουλάχιστον 10 χαρακτήρες, τουλάχιστον 1 μικρό, 1 κεφαλαίο, 1 αριθμός, 1 σύμβολο, χωρίς κενά.';
    }

    public function index(): void {
        Auth::requireRole('admin');

        $groups = $this->db->query('SELECT id, name FROM `groups` WHERE is_current=1 ORDER BY name')->fetchAll();

        $this->render('users/index', [
            'pageTitle' => 'Ορισμός Χρηστών',
            'groups'    => $groups,
        ]);
    }

    public function apiList(): void {
        Auth::requireRole('admin');

        $page     = max(1, (int)($_POST['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($_POST['page_size'] ?? 20)));
        $offset   = ($page - 1) * $pageSize;

        $total = (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.name, u.email, u.role, u.active,
                    GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS teacher_groups
             FROM users u
             LEFT JOIN teacher_groups tg ON tg.user_id = u.id
             LEFT JOIN `groups` g ON g.id = tg.group_id
             GROUP BY u.id
             ORDER BY u.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$pageSize, $offset]);

        $this->json(['total' => $total, 'rows' => $stmt->fetchAll()]);
    }

    public function apiSave(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $currentUser = Auth::user();

        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = in_array($_POST['role'] ?? '', ['admin', 'teacher', 'parent'], true) ? $_POST['role'] : 'teacher';
        $active   = ((string)($_POST['active'] ?? '') === '1') ? 1 : 0;
        $password = (string)($_POST['password'] ?? '');

        $groupIds = $_POST['group_ids'] ?? [];
        if (!is_array($groupIds)) $groupIds = [];
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), fn($v) => $v > 0)));

        if ($name === '' || $username === '') {
            $this->json(['error' => 'Το όνομα και το username είναι υποχρεωτικά.'], 422);
            return;
        }
        if (!Auth::isValidUsername($username)) {
            $this->json(['error' => 'Μη έγκυρο username. ' . $this->usernamePolicyText()], 422);
            return;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Μη έγκυρο email.'], 422);
            return;
        }

        if ($id > 0) {
            $exists = $this->db->prepare('SELECT id FROM users WHERE id=?');
            $exists->execute([$id]);
            if (!$exists->fetch()) {
                $this->json(['error' => 'Ο χρήστης δεν βρέθηκε.'], 404);
                return;
            }
        }

        $taken = $this->db->prepare('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1');
        $taken->execute([$username, $id]);
        if ($taken->fetch()) {
            $this->json(['error' => 'Το username χρησιμοποιείται ήδη.'], 422);
            return;
        }

        if ($id === (int)$currentUser['id']) {
            if ($role !== 'admin') {
                $this->json(['error' => 'Δεν μπορείτε να αφαιρέσετε τον ρόλο admin από τον δικό σας λογαριασμό.'], 422);
                return;
            }
            if ($active !== 1) {
                $this->json(['error' => 'Δεν μπορείτε να απενεργοποιήσετε τον δικό σας λογαριασμό.'], 422);
                return;
            }
        }

        if ($id > 0) {
            $sets = 'name=?, username=?, email=?, role=?, active=?';
            $binds = [$name, $username, $email, $role, $active];

            if ($password !== '') {
                $passErr = Auth::validatePasswordStrength($password);
                if ($passErr !== '') {
                    $this->json(['error' => 'Μη ασφαλής κωδικός. ' . $this->passwordPolicyText()], 422);
                    return;
                }
                $sets .= ', password=?';
                $binds[] = Auth::hashPassword($password);
            }
            $binds[] = $id;

            $this->db->prepare("UPDATE users SET $sets WHERE id=?")->execute($binds);
            $userId = $id;
        } else {
            $passErr = Auth::validatePasswordStrength($password);
            if ($passErr !== '') {
                $this->json(['error' => 'Μη ασφαλής κωδικός. ' . $this->passwordPolicyText()], 422);
                return;
            }

            $this->db->prepare(
                'INSERT INTO users (username, password, name, email, role, active) VALUES (?,?,?,?,?,?)'
            )->execute([$username, Auth::hashPassword($password), $name, $email, $role, $active]);

            $userId = (int)$this->db->lastInsertId();
        }

        $this->db->prepare('DELETE FROM teacher_groups WHERE user_id=?')->execute([$userId]);
        if ($role === 'teacher') {
            foreach ($groupIds as $gid) {
                $this->db->prepare('INSERT INTO teacher_groups (user_id, group_id) VALUES (?,?)')
                         ->execute([$userId, $gid]);
            }
        }

        $this->json(['success' => true, 'id' => $userId]);
    }

    public function apiSetActive(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $id     = (int)($_POST['id'] ?? 0);
        $active = ((string)($_POST['active'] ?? '') === '1') ? 1 : 0;
        $user   = Auth::user();

        if ($id <= 0) {
            $this->json(['error' => 'Μη έγκυρο ID.'], 422);
            return;
        }

        if ($id === (int)$user['id'] && $active !== 1) {
            $this->json(['error' => 'Δεν μπορείτε να απενεργοποιήσετε τον δικό σας λογαριασμό.'], 422);
            return;
        }

        $stmt = $this->db->prepare('UPDATE users SET active=? WHERE id=?');
        $stmt->execute([$active, $id]);
        $this->json(['success' => true]);
    }

    public function apiDelete(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $user = Auth::user();

        if ($id <= 0) {
            $this->json(['error' => 'Μη έγκυρο ID.'], 422);
            return;
        }

        if ($id === (int)$user['id']) {
            $this->json(['error' => 'Δεν μπορείτε να διαγράψετε τον δικό σας λογαριασμό.'], 422);
            return;
        }

        $stmt = $this->db->prepare('SELECT role, active FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            $this->json(['error' => 'Ο χρήστης δεν βρέθηκε.'], 404);
            return;
        }

        if ($target['role'] === 'admin' && (int)$target['active'] === 1) {
            $activeAdmins = (int)$this->db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1")->fetchColumn();
            if ($activeAdmins <= 1) {
                $this->json(['error' => 'Δεν μπορείτε να διαγράψετε τον τελευταίο ενεργό admin.'], 422);
                return;
            }
        }

        $this->db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        $this->json(['success' => true]);
    }

    public function apiGroupsForUser(): void {
        Auth::requireRole('admin');

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['error' => 'Μη έγκυρο ID χρήστη.'], 422);
            return;
        }

        $stmt = $this->db->prepare('SELECT role FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $role = (string)$stmt->fetchColumn();

        if ($role !== 'teacher') {
            $this->json(['group_ids' => []]);
            return;
        }

        $stmt = $this->db->prepare('SELECT group_id FROM teacher_groups WHERE user_id=?');
        $stmt->execute([$userId]);
        $ids = array_map('intval', array_column($stmt->fetchAll(), 'group_id'));

        $this->json(['group_ids' => $ids]);
    }
}
