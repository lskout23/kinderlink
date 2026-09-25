<?php
class PersonalController extends Controller {

    public function index(): void {
        Auth::requireLogin();
        $user = Auth::user();
        $this->render('personal/index', [
            'pageTitle' => 'Προσωπικά Στοιχεία',
            'user'      => $user,
        ]);
    }

    public function save(): void {
        Auth::requireLogin();
        $this->verifyCsrf();

        $user      = Auth::user();
        $formMode  = (string)($_POST['form_mode'] ?? 'combined');
        $name      = trim($_POST['name']     ?? '');
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email']    ?? '');
        $currPass  = $_POST['current_password'] ?? '';
        $newPass   = $_POST['new_password']  ?? '';
        $confPass  = $_POST['confirm_password'] ?? '';

        $errors = [];

        if (!in_array($formMode, ['profile', 'password', 'combined'], true)) {
            $errors[] = 'Μη έγκυρη ενέργεια αποθήκευσης.';
        }

        if ($formMode === 'profile' || $formMode === 'combined') {
            if ($name === '')     $errors[] = 'Το όνομα είναι υποχρεωτικό.';
            if ($username === '') $errors[] = 'Το όνομα χρήστη είναι υποχρεωτικό.';
            if ($username !== '' && !Auth::isValidUsername($username)) {
                $errors[] = 'Το username πρέπει να έχει 4-50 χαρακτήρες και να περιέχει μόνο λατινικά γράμματα, αριθμούς, @, τελεία, κάτω παύλα ή παύλα.';
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Μη έγκυρη διεύθυνση email.';
            }

            if ($username !== '') {
                $taken = $this->db->prepare('SELECT id FROM users WHERE username=? AND id<>?');
                $taken->execute([$username, $user['id']]);
                if ($taken->fetch()) $errors[] = 'Το username χρησιμοποιείται ήδη.';
            }
        }

        if ($formMode === 'password' || $formMode === 'combined') {
            if ($formMode === 'password' && trim($newPass) === '' && trim($confPass) === '') {
                $errors[] = 'Συμπληρώστε νέο κωδικό για να γίνει αλλαγή.';
            }

            if ($newPass !== '') {
                if (trim($currPass) === '') {
                    $errors[] = 'Για αλλαγή κωδικού απαιτείται ο τρέχων κωδικός.';
                }

                $passErr = Auth::validatePasswordStrength($newPass);
                if ($passErr !== '') $errors[] = $passErr;

                $row = $this->db->prepare('SELECT password FROM users WHERE id=? LIMIT 1');
                $row->execute([$user['id']]);
                $storedHash = (string)$row->fetchColumn();
                if ($storedHash === '' || !Auth::verifyPassword($currPass, $storedHash)) {
                    $errors[] = 'Ο τρέχων κωδικός δεν είναι σωστός.';
                }
            }

            if ($newPass !== $confPass) {
                $errors[] = 'Οι κωδικοί δεν ταιριάζουν.';
            }
        }

        if ($errors) {
            $userView = $user;
            if ($formMode === 'profile' || $formMode === 'combined') {
                $userView = array_merge($user, compact('name', 'username', 'email'));
            }

            $this->render('personal/index', [
                'pageTitle' => 'Προσωπικά Στοιχεία',
                'user'      => $userView,
                'errors'    => $errors,
            ]);
            return;
        }

        if ($formMode === 'password') {
            $this->db->prepare('UPDATE users SET password=?, temp_password_expires=NULL WHERE id=?')
                ->execute([Auth::hashPassword($newPass), $user['id']]);
        } elseif ($formMode === 'combined' && $newPass !== '') {
            // The personal-details form submits both sections. Save them atomically.
            $this->db->prepare('UPDATE users SET name=?, username=?, email=?, password=?, temp_password_expires=NULL WHERE id=?')
                ->execute([$name, $username, $email, Auth::hashPassword($newPass), $user['id']]);
        } else {
            $this->db->prepare('UPDATE users SET name=?, username=?, email=? WHERE id=?')
                ->execute([$name, $username, $email, $user['id']]);
        }

        // Refresh session user data
        $updated = $this->db->prepare('SELECT id, username, name, email, role FROM users WHERE id=?');
        $updated->execute([$user['id']]);
        Auth::login($updated->fetch());

        $target = (($user['role'] ?? '') === 'parent')
            ? '/parent/personal-details?saved=1'
            : '/administration/personal-details?saved=1';
        $this->redirect($target);
    }
}
