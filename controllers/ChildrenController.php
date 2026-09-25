<?php
class ChildrenController extends Controller {

    private const CLONE_SUFFIX = ' (Copy)';

    public function index(): void {
        Auth::requireRole('admin');

        $parents = $this->db->query(
            "SELECT id, username, name FROM users WHERE role='parent' AND active=1 ORDER BY name, username"
        )->fetchAll();

        $this->render('children/index', [
            'pageTitle' => 'Ορισμός Παιδιών',
            'parents'   => $parents,
        ]);
    }

    /** API: paginated list with optional search */
    public function apiList(): void {
        Auth::requireRole('admin');

        $page     = max(1, (int)($_POST['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($_POST['page_size'] ?? 16)));
        $search   = trim($_POST['search'] ?? '');
        $offset   = ($page - 1) * $pageSize;

        $where  = '1=1';
        $params = [];
        if ($search !== '') {
            $where  = '(first_name LIKE ? OR last_name LIKE ? OR email1 LIKE ? OR email2 LIKE ?)';
            $s      = '%' . $search . '%';
            $params = [$s, $s, $s, $s];
        }

        $total = $this->db->prepare("SELECT COUNT(*) FROM children WHERE $where");
        $total->execute($params);
        $total = (int)$total->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT c.id, c.first_name, c.last_name, c.dob, c.mother_mobile, c.father_mobile,
                    c.email1, c.email2, c.send_email1, c.send_email2, c.active,
                    c.parent_user_id, u.name AS parent_name, u.username AS parent_username
             FROM children c
             LEFT JOIN users u ON u.id = c.parent_user_id
             WHERE $where ORDER BY c.last_name, c.first_name
             LIMIT $pageSize OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $this->json(['total' => $total, 'rows' => $rows]);
    }

    /** API: insert or update a child */
    public function apiSave(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $id          = (int)($_POST['id'] ?? 0);
        $firstName   = trim($_POST['first_name'] ?? '');
        $lastName    = trim($_POST['last_name'] ?? '');
        $dob         = $_POST['dob'] ?? null;
        $motherMobile= trim($_POST['mother_mobile'] ?? '');
        $fatherMobile= trim($_POST['father_mobile'] ?? '');
        $email1      = trim($_POST['email1'] ?? '');
        $email2      = trim($_POST['email2'] ?? '');
        $sendEmail1  = ((string)($_POST['send_email1'] ?? '') === '1') ? 1 : 0;
        $sendEmail2  = ((string)($_POST['send_email2'] ?? '') === '1') ? 1 : 0;
        $active      = ((string)($_POST['active'] ?? '') === '1') ? 1 : 0;
        $parentUserId = (int)($_POST['parent_user_id'] ?? 0);
        if ($parentUserId <= 0) {
            $parentUserId = null;
        }

        if ($firstName === '' || $lastName === '') {
            $this->json(['error' => 'Το όνομα και επώνυμο είναι υποχρεωτικά.'], 422);
            return;
        }

        // Validate emails
        foreach ([$email1, $email2] as $em) {
            if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $this->json(['error' => 'Μη έγκυρη διεύθυνση email: ' . $em], 422);
                return;
            }
        }

        // Sanitize dob
        if ($dob === '') $dob = null;

        if ($parentUserId !== null) {
            $parentStmt = $this->db->prepare('SELECT id FROM users WHERE id=? AND role=\'parent\' AND active=1 LIMIT 1');
            $parentStmt->execute([$parentUserId]);
            if (!$parentStmt->fetchColumn()) {
                $this->json(['error' => 'Μη έγκυρος parent λογαριασμός.'], 422);
                return;
            }
        }

        try {
            if ($id > 0) {
                $stmt = $this->db->prepare(
                    'UPDATE children SET first_name=?, last_name=?, dob=?, mother_mobile=?,
                     father_mobile=?, email1=?, email2=?, send_email1=?, send_email2=?, active=?, parent_user_id=?
                     WHERE id=?'
                );
                $stmt->execute([$firstName, $lastName, $dob, $motherMobile, $fatherMobile,
                                $email1, $email2, $sendEmail1, $sendEmail2, $active, $parentUserId, $id]);
                $this->json(['success' => true, 'id' => $id]);
                return;
            }

            $stmt = $this->db->prepare(
                'INSERT INTO children (first_name, last_name, dob, mother_mobile, father_mobile,
                 email1, email2, send_email1, send_email2, active, parent_user_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$firstName, $lastName, $dob, $motherMobile, $fatherMobile,
                            $email1, $email2, $sendEmail1, $sendEmail2, $active, $parentUserId]);
            $this->json(['success' => true, 'id' => $this->db->lastInsertId()]);
        } catch (Throwable $e) {
            $this->json(['error' => 'Αποτυχία αποθήκευσης παιδιού: ' . $e->getMessage()], 500);
        }
    }

    public function apiBulkAction(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $action = $_POST['action'] ?? null;
        if (!is_string($action) || !in_array($action, ['delete', 'activate', 'deactivate', 'clone'], true)) {
            $this->json(['error' => 'Μη έγκυρη ενέργεια.'], 422);
            return;
        }

        $idsRaw = $_POST['ids'] ?? null;
        if (!is_array($idsRaw) || !array_is_list($idsRaw) || count($idsRaw) < 1 || count($idsRaw) > 100) {
            $this->json(['error' => 'Επιλέξτε από 1 έως 100 έγκυρα ID παιδιών.'], 422);
            return;
        }
        $ids = [];
        foreach ($idsRaw as $id) {
            if ((!is_int($id) && !is_string($id))
                || !preg_match('/^[1-9][0-9]*$/D', (string)$id)
                || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $this->json(['error' => 'Μη έγκυρο ID παιδιού.'], 422);
                return;
            }
            $ids[(int)$id] = (int)$id;
        }
        $ids = array_values($ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $cloneTransaction = false;

        try {
            if ($action === 'delete') {
                // Keep existing database FK behavior; do not purge photo files.
                $stmt = $this->db->prepare('DELETE FROM children WHERE id IN (' . $placeholders . ')');
                $stmt->execute($ids);
                $affected = $stmt->rowCount();
            } elseif ($action === 'activate' || $action === 'deactivate') {
                $active = $action === 'activate' ? 1 : 0;
                $stmt = $this->db->prepare('UPDATE children SET active = ? WHERE id IN (' . $placeholders . ')');
                $stmt->execute(array_merge([$active], $ids));
                $affected = $stmt->rowCount();
            } else {
                // InnoDB: commit all copies together, or roll back every copy.
                $this->db->beginTransaction();
                $cloneTransaction = true;
                $stmt = $this->db->prepare(
                    'SELECT first_name, last_name, dob, mother_mobile, father_mobile,
                            email1, email2, send_email1, send_email2, active, parent_user_id
                     FROM children WHERE id IN (' . $placeholders . ') ORDER BY last_name, first_name'
                );
                $stmt->execute($ids);
                $rows = $stmt->fetchAll();

                $insert = $this->db->prepare(
                    'INSERT INTO children (first_name, last_name, dob, mother_mobile, father_mobile,
                     email1, email2, send_email1, send_email2, active, parent_user_id)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );

                $affected = 0;
                foreach ($rows as $row) {
                    $insert->execute([
                        $row['first_name'],
                        $this->cloneLastName((string)$row['last_name']),
                        $row['dob'],
                        $row['mother_mobile'],
                        $row['father_mobile'],
                        $row['email1'],
                        $row['email2'],
                        $row['send_email1'],
                        $row['send_email2'],
                        $row['active'],
                        $row['parent_user_id'] !== '' ? $row['parent_user_id'] : null,
                    ]);
                    $affected++;
                }
                $this->db->commit();
                $cloneTransaction = false;
            }
        } catch (Throwable $e) {
            if ($cloneTransaction) {
                try {
                    if ($this->db->inTransaction()) $this->db->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log('Children bulk clone rollback failed.');
                }
            }
            // Never expose SQL, child data, connection details or exception text.
            error_log('Children bulk action failed.');
            $this->json(['error' => 'Αποτυχία μαζικής ενέργειας.'], 500);
            return;
        }
        $this->json(['success' => true, 'affected' => $affected]);
    }

    /** API: delete a child */
    public function apiDelete(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['error' => 'Μη έγκυρο ID.'], 422);
            return;
        }

        $this->db->prepare('DELETE FROM children WHERE id=?')->execute([$id]);
        $this->json(['success' => true]);
    }

    private function cloneLastName(string $lastName): string {
        $suffix = self::CLONE_SUFFIX;
        $base = trim($lastName);
        $maxLen = 100 - strlen($suffix);
        if (function_exists('mb_substr')) {
            $base = mb_substr($base, 0, $maxLen, 'UTF-8');
        } else {
            $base = substr($base, 0, $maxLen);
        }
        return rtrim($base) . $suffix;
    }
}
