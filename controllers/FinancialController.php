<?php
class FinancialController extends Controller {

    public function income(): void {
        Auth::requireRole('admin');

        // Get all current children grouped by their group
        $groups = $this->db->query('SELECT id, name FROM `groups` WHERE is_current=1 ORDER BY name')->fetchAll();
        $allChildren = $this->db->query(
            'SELECT id, first_name, last_name FROM children WHERE active=1 ORDER BY last_name, first_name'
        )->fetchAll();
        $financialActivities = $this->db->query('SELECT id, name FROM financial_activities ORDER BY name')->fetchAll();

        $this->render('financial/income', [
            'pageTitle'           => 'Έσοδα ανά Παιδί',
            'groups'              => $groups,
            'allChildren'         => $allChildren,
            'financialActivities' => $financialActivities,
        ]);
    }

    public function apiIncomeList(): void {
        Auth::requireRole('admin');

        $childId = (int)($_POST['child_id'] ?? 0);
        if ($childId <= 0) { $this->json(['error' => 'Μη έγκυρο παιδί.'], 422); return; }

        // Pending (balance remaining)
        $pending = $this->db->prepare(
            'SELECT i.*, fa.name AS activity_name FROM income i
             LEFT JOIN financial_activities fa ON fa.id = i.activity_id
             WHERE i.child_id = ? AND i.amount_paid < i.amount
             ORDER BY i.entry_date DESC'
        );
        $pending->execute([$childId]);

        // Full record
        $full = $this->db->prepare(
            'SELECT i.*, fa.name AS activity_name FROM income i
             LEFT JOIN financial_activities fa ON fa.id = i.activity_id
             WHERE i.child_id = ?
             ORDER BY i.entry_date DESC LIMIT 100'
        );
        $full->execute([$childId]);

        $this->json(['pending' => $pending->fetchAll(), 'full' => $full->fetchAll()]);
    }

    public function apiIncomeSave(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $id         = (int)($_POST['id']          ?? 0);
        $childId    = (int)($_POST['child_id']     ?? 0);
        $activityId = (int)($_POST['activity_id']  ?? 0) ?: null;
        $desc       = trim($_POST['description']   ?? '');
        $entryDate  = $_POST['entry_date']         ?? date('Y-m-d');
        $collectDate = $_POST['collection_date']   ?? null;
        $amount     = max(0, (float)($_POST['amount']      ?? 0));
        $amountPaid = max(0, (float)($_POST['amount_paid'] ?? 0));
        $recurrence = max(1, (int)($_POST['recurrence']    ?? 1));
        $periodType = in_array($_POST['period_type'] ?? '', ['Μέρες','Εβδομάδες','Μήνες']) ? $_POST['period_type'] : 'Μέρες';
        $notes      = trim($_POST['notes'] ?? '');

        if ($childId <= 0) { $this->json(['error' => 'Επιλέξτε παιδί.'], 422); return; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) $entryDate = date('Y-m-d');
        if ($collectDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $collectDate)) $collectDate = null;

        if ($id > 0) {
            $this->db->prepare(
                'UPDATE income SET child_id=?,activity_id=?,description=?,entry_date=?,
                 collection_date=?,amount=?,amount_paid=?,recurrence=?,period_type=?,notes=?
                 WHERE id=?'
            )->execute([$childId,$activityId,$desc,$entryDate,$collectDate,$amount,$amountPaid,$recurrence,$periodType,$notes,$id]);
            $this->json(['success'=>true,'id'=>$id]);
        } else {
            $this->db->prepare(
                'INSERT INTO income (child_id,activity_id,description,entry_date,collection_date,
                 amount,amount_paid,recurrence,period_type,notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$childId,$activityId,$desc,$entryDate,$collectDate,$amount,$amountPaid,$recurrence,$periodType,$notes]);
            $this->json(['success'=>true,'id'=>$this->db->lastInsertId()]);
        }
    }

    public function apiIncomeDelete(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->json(['error'=>'Μη έγκυρο ID.'],422); return; }
        $this->db->prepare('DELETE FROM income WHERE id=?')->execute([$id]);
        $this->json(['success'=>true]);
    }

    // ── Income Totals ─────────────────────────────────────────────────

    public function incomeTotals(): void {
        Auth::requireRole('admin');
        $this->render('financial/income-totals', ['pageTitle' => 'Σύνολα Εσόδων']);
    }

    public function apiIncomeTotalsList(): void {
        Auth::requireRole('admin');

        $from = $_POST['from'] ?? date('Y-m-01');
        $to   = $_POST['to']   ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

        $stmt = $this->db->prepare(
            'SELECT c.first_name, c.last_name,
                    SUM(i.amount) AS total_charged,
                    SUM(i.amount_paid) AS total_paid,
                    SUM(i.amount - i.amount_paid) AS balance
             FROM income i
             JOIN children c ON c.id = i.child_id
             WHERE i.entry_date BETWEEN ? AND ?
             GROUP BY i.child_id
             ORDER BY c.last_name, c.first_name'
        );
        $stmt->execute([$from, $to]);
        $this->json(['rows' => $stmt->fetchAll()]);
    }

    // ── Financial Activities Setup ─────────────────────────────────────

    public function setupActivities(): void {
        Auth::requireRole('admin');
        $this->render('financial/setup-activities', ['pageTitle' => 'Ρύθμιση Οικονομικών Δραστηριοτήτων']);
    }

    public function apiActivitiesList(): void {
        Auth::requireRole('admin');
        $rows = $this->db->query('SELECT id, name FROM financial_activities ORDER BY name')->fetchAll();
        $this->json(['rows' => $rows]);
    }

    public function apiActivitiesSave(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $this->json(['error'=>'Το όνομα είναι υποχρεωτικό.'],422); return; }
        if ($id > 0) {
            $this->db->prepare('UPDATE financial_activities SET name=? WHERE id=?')->execute([$name,$id]);
        } else {
            $this->db->prepare('INSERT INTO financial_activities (name) VALUES (?)')->execute([$name]);
            $id = $this->db->lastInsertId();
        }
        $this->json(['success'=>true,'id'=>$id]);
    }

    public function apiActivitiesDelete(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->json(['error'=>'Μη έγκυρο ID.'],422); return; }
        $this->db->prepare('DELETE FROM financial_activities WHERE id=?')->execute([$id]);
        $this->json(['success'=>true]);
    }

    // ── Expenses ──────────────────────────────────────────────────────

    public function expenses(): void {
        Auth::requireRole('admin');
        $financialActivities = $this->db->query('SELECT id, name FROM financial_activities ORDER BY name')->fetchAll();
        $this->render('financial/expenses', [
            'pageTitle'           => 'Έξοδα',
            'financialActivities' => $financialActivities,
        ]);
    }

    public function apiExpensesList(): void {
        Auth::requireRole('admin');
        $from = $_POST['from'] ?? date('Y-m-01');
        $to   = $_POST['to']   ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

        $stmt = $this->db->prepare(
            'SELECT e.*, fa.name AS activity_name FROM expenses e
             LEFT JOIN financial_activities fa ON fa.id = e.activity_id
             WHERE e.entry_date BETWEEN ? AND ?
             ORDER BY e.entry_date DESC LIMIT 200'
        );
        $stmt->execute([$from, $to]);
        $this->json(['rows' => $stmt->fetchAll()]);
    }

    public function apiExpensesSave(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();
        $id         = (int)($_POST['id'] ?? 0);
        $activityId = (int)($_POST['activity_id'] ?? 0) ?: null;
        $entryDate  = $_POST['entry_date'] ?? date('Y-m-d');
        $amount     = max(0, (float)($_POST['amount'] ?? 0));
        $notes      = trim($_POST['notes'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) $entryDate = date('Y-m-d');

        if ($id > 0) {
            $this->db->prepare('UPDATE expenses SET activity_id=?,entry_date=?,amount=?,notes=? WHERE id=?')
                     ->execute([$activityId,$entryDate,$amount,$notes,$id]);
        } else {
            $this->db->prepare('INSERT INTO expenses (activity_id,entry_date,amount,notes) VALUES (?,?,?,?)')
                     ->execute([$activityId,$entryDate,$amount,$notes]);
            $id = $this->db->lastInsertId();
        }
        $this->json(['success'=>true,'id'=>$id]);
    }

    public function apiExpensesDelete(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->json(['error'=>'Μη έγκυρο ID.'],422); return; }
        $this->db->prepare('DELETE FROM expenses WHERE id=?')->execute([$id]);
        $this->json(['success'=>true]);
    }
}
