<?php
class InboxController extends Controller {

    // ── Views ─────────────────────────────────────────────────────────

    /** Inbox page for parents, teachers, and admins */
    public function index(): void {
        Auth::requireLogin();
        $user = Auth::user();

        $unread = $this->countUnread($user);

        $this->render('inbox/index', [
            'pageTitle' => 'Εισερχόμενα',
            'user'      => $user,
            'unread'    => $unread,
        ]);
    }

    // ── API ───────────────────────────────────────────────────────────

    /** List threads for the current user */
    public function apiThreadList(): void {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['role'] === 'parent') {
            $threads = $this->getThreadsForParent((int)$user['id']);
        } elseif ($user['role'] === 'teacher') {
            $threads = $this->getThreadsForTeacher((int)$user['id']);
        } else {
            // admin — all threads
            $threads = $this->getAllThreads();
        }

        $this->json(['threads' => $threads]);
    }

    /** Get all messages in a thread (and mark them as read) */
    public function apiThreadMessages(): void {
        Auth::requireLogin();
        $this->verifyCsrf();

        $user     = Auth::user();
        $threadId = (int)($_POST['thread_id'] ?? 0);

        if ($threadId <= 0) { $this->json(['error' => 'Μη έγκυρο thread.'], 422); return; }
        if (!$this->canAccessThread($threadId, $user)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση.'], 403);
            return;
        }

        // Mark unread messages (sent by the OTHER party) as read
        $this->db->prepare(
            'UPDATE parent_thread_messages
             SET read_at = NOW()
             WHERE thread_id = ? AND sender_id <> ? AND read_at IS NULL'
        )->execute([$threadId, $user['id']]);

        $stmt = $this->db->prepare(
            'SELECT m.id, m.body, m.created_at, m.sender_id,
                    u.name AS sender_name, u.role AS sender_role
             FROM parent_thread_messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.thread_id = ?
             ORDER BY m.created_at ASC'
        );
        $stmt->execute([$threadId]);
        $messages = $stmt->fetchAll();

        // Thread meta
        $meta = $this->db->prepare(
            'SELECT t.subject, t.group_id, t.child_id,
                    c.first_name, c.last_name, g.name AS group_name,
                    u.name AS parent_name
             FROM parent_threads t
             JOIN children c ON c.id = t.child_id
             JOIN `groups`  g ON g.id = t.group_id
             JOIN users     u ON u.id = t.parent_user_id
             WHERE t.id = ?'
        );
        $meta->execute([$threadId]);
        $thread = $meta->fetch();

        $this->json(['messages' => $messages, 'thread' => $thread]);
    }

    /** Parent: open/create a thread and send the first message */
    public function apiSendMessage(): void {
        Auth::requireRole('parent');
        $this->verifyCsrf();

        $user    = Auth::user();
        $childId = (int)($_POST['child_id'] ?? 0);
        $groupId = (int)($_POST['group_id'] ?? 0);
        $subject = trim($_POST['subject']   ?? '');
        $body    = trim($_POST['body']      ?? '');

        if ($childId <= 0 || $groupId <= 0) { $this->json(['error' => 'Μη έγκυρο παιδί/τμήμα.'], 422); return; }
        if ($body === '')    { $this->json(['error' => 'Το μήνυμα δεν μπορεί να είναι κενό.'],     422); return; }
        if (mb_strlen($body) > 2000) { $this->json(['error' => 'Το μήνυμα υπερβαίνει τους 2000 χαρακτήρες.'], 422); return; }
        if ($subject === '') $subject = 'Ερώτηση γονέα';

        // Verify parent owns this child
        if (!$this->parentOwnsChild((int)$user['id'], $childId)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση σε αυτό το παιδί.'], 403);
            return;
        }

        // Find or create thread
        $threadId = $this->findOrCreateThread($childId, (int)$user['id'], $groupId, $subject);

        // Insert message
        $this->db->prepare(
            'INSERT INTO parent_thread_messages (thread_id, sender_id, body) VALUES (?,?,?)'
        )->execute([$threadId, $user['id'], $body]);

        // Update thread updated_at
        $this->db->prepare('UPDATE parent_threads SET updated_at=NOW() WHERE id=?')->execute([$threadId]);

        // Email notification to teacher(s)
        $this->notifyTeachers($threadId, $childId, $groupId, $user['name'], $body);

        $this->json(['success' => true, 'thread_id' => $threadId]);
    }

    /** Any authenticated user can reply to a thread they have access to */
    public function apiReply(): void {
        Auth::requireLogin();
        $this->verifyCsrf();

        $user     = Auth::user();
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $body     = trim($_POST['body']       ?? '');

        if ($threadId <= 0)          { $this->json(['error' => 'Μη έγκυρο thread.'],                         422); return; }
        if ($body === '')            { $this->json(['error' => 'Το μήνυμα δεν μπορεί να είναι κενό.'],       422); return; }
        if (mb_strlen($body) > 2000) { $this->json(['error' => 'Το μήνυμα υπερβαίνει τους 2000 χαρακτήρες.'], 422); return; }

        if (!$this->canAccessThread($threadId, $user)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση σε αυτό το thread.'], 403);
            return;
        }

        $this->db->prepare(
            'INSERT INTO parent_thread_messages (thread_id, sender_id, body) VALUES (?,?,?)'
        )->execute([$threadId, $user['id'], $body]);

        $this->db->prepare('UPDATE parent_threads SET updated_at=NOW() WHERE id=?')->execute([$threadId]);

        // Email notifications
        if ($user['role'] === 'parent') {
            // Parent replied → notify teacher(s)
            $thread = $this->db->prepare('SELECT child_id, group_id FROM parent_threads WHERE id=?');
            $thread->execute([$threadId]);
            $tr = $thread->fetch();
            if ($tr) $this->notifyTeachers($threadId, (int)$tr['child_id'], (int)$tr['group_id'], $user['name'], $body);
        } else {
            // Teacher/admin replied → notify parent
            $this->notifyParent($threadId, $user['name'], $body);
        }

        $this->json(['success' => true]);
    }

    /** Parent: get children + groups for starting a new thread */
    public function apiChildrenForInbox(): void {
        Auth::requireRole('parent');
        $user = Auth::user();

        $stmt = $this->db->prepare(
            'SELECT c.id AS child_id, c.first_name, c.last_name,
                    g.id AS group_id, g.name AS group_name,
                    u.name AS teacher_name
             FROM children c
             JOIN children_groups cg ON cg.child_id = c.id
             JOIN `groups` g ON g.id = cg.group_id AND g.is_current = 1
             LEFT JOIN teacher_groups tg ON tg.group_id = g.id
             LEFT JOIN users u ON u.id = tg.user_id AND u.role = \'teacher\'
                         WHERE c.parent_user_id = ? AND c.active = 1
             ORDER BY c.last_name, c.first_name, g.name'
        );
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll();

        $this->json(['children' => $rows]);
    }

    /** Delete a single message (sender or admin only). Deletes thread if no messages remain. */
    public function apiDeleteMessage(): void {
        Auth::requireLogin();
        $this->verifyCsrf();

        $user  = Auth::user();
        $msgId = (int)($_POST['msg_id'] ?? 0);
        if ($msgId <= 0) { $this->json(['error' => 'Μη έγκυρο μήνυμα.'], 422); return; }

        // Fetch message to verify ownership
        $stmt = $this->db->prepare('SELECT sender_id, thread_id FROM parent_thread_messages WHERE id=?');
        $stmt->execute([$msgId]);
        $msg = $stmt->fetch();

        if (!$msg) { $this->json(['error' => 'Το μήνυμα δεν βρέθηκε.'], 404); return; }

        // Only sender or admin can delete
        if ($user['role'] !== 'admin' && (int)$msg['sender_id'] !== (int)$user['id']) {
            $this->json(['error' => 'Δεν έχετε δικαίωμα διαγραφής αυτού του μηνύματος.'], 403);
            return;
        }

        $threadId = (int)$msg['thread_id'];

        // Sending a message previously does not preserve access after unlinking.
        if ($user['role'] === 'parent' && !$this->canAccessThread($threadId, $user)) {
            $this->json(['error' => 'Δεν έχετε πρόσβαση σε αυτό το thread.'], 403);
            return;
        }

        // Delete the message
        $this->db->prepare('DELETE FROM parent_thread_messages WHERE id=?')->execute([$msgId]);

        // Check if thread has remaining messages
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM parent_thread_messages WHERE thread_id=?');
        $countStmt->execute([$threadId]);
        $remaining = (int)$countStmt->fetchColumn();        $threadDeleted = false;
        if ((int)$remaining === 0) {
            $this->db->prepare('DELETE FROM parent_threads WHERE id=?')->execute([$threadId]);
            $threadDeleted = true;
        } else {
            // Update thread updated_at to latest message
            $this->db->prepare('UPDATE parent_threads SET updated_at=NOW() WHERE id=?')->execute([$threadId]);
        }

        $this->json(['success' => true, 'thread_deleted' => $threadDeleted]);
    }

    /** Delete an entire thread (admin only, or thread owner parent) */
    // ── Helpers ───────────────────────────────────────────────────────

    private function getThreadsForParent(int $parentId): array {
        $stmt = $this->db->prepare(
            'SELECT t.id, t.subject, t.updated_at,
                    c.first_name, c.last_name, g.name AS group_name,
                    (SELECT COUNT(*) FROM parent_thread_messages m
                     WHERE m.thread_id = t.id AND m.sender_id <> ? AND m.read_at IS NULL) AS unread,
                    (SELECT body FROM parent_thread_messages m2
                     WHERE m2.thread_id = t.id ORDER BY m2.created_at DESC LIMIT 1) AS last_body
             FROM parent_threads t
             JOIN children c ON c.id = t.child_id
             JOIN `groups`  g ON g.id = t.group_id
                         WHERE t.parent_user_id = ? AND c.parent_user_id = ? AND c.active = 1
             ORDER BY t.updated_at DESC'
        );
        $stmt->execute([$parentId, $parentId, $parentId]);
        return $stmt->fetchAll();
    }

    private function getThreadsForTeacher(int $teacherId): array {
        // Show threads for groups the teacher is assigned to, OR threads they've already replied to
        $stmt = $this->db->prepare(
            'SELECT DISTINCT t.id, t.subject, t.updated_at,
                    c.first_name, c.last_name, g.name AS group_name,
                    pu.name AS parent_name,
                    (SELECT COUNT(*) FROM parent_thread_messages m
                     WHERE m.thread_id = t.id AND m.sender_id <> ? AND m.read_at IS NULL) AS unread,
                    (SELECT body FROM parent_thread_messages m2
                     WHERE m2.thread_id = t.id ORDER BY m2.created_at DESC LIMIT 1) AS last_body
             FROM parent_threads t
             JOIN children c  ON c.id = t.child_id
             JOIN `groups`  g ON g.id = t.group_id
             JOIN users pu    ON pu.id = t.parent_user_id
             WHERE t.group_id IN (SELECT group_id FROM teacher_groups WHERE user_id = ?)
                OR t.id IN (SELECT DISTINCT thread_id FROM parent_thread_messages WHERE sender_id = ?)
             ORDER BY t.updated_at DESC'
        );
        $stmt->execute([$teacherId, $teacherId, $teacherId]);
        return $stmt->fetchAll();
    }

    private function getAllThreads(): array {
        $stmt = $this->db->query(
            'SELECT t.id, t.subject, t.updated_at,
                    c.first_name, c.last_name, g.name AS group_name,
                    pu.name AS parent_name,
                    (SELECT COUNT(*) FROM parent_thread_messages m
                     WHERE m.thread_id = t.id AND m.read_at IS NULL) AS unread,
                    (SELECT body FROM parent_thread_messages m2
                     WHERE m2.thread_id = t.id ORDER BY m2.created_at DESC LIMIT 1) AS last_body
             FROM parent_threads t
             JOIN children c  ON c.id = t.child_id
             JOIN `groups`  g ON g.id = t.group_id
             JOIN users pu    ON pu.id = t.parent_user_id
             ORDER BY t.updated_at DESC'
        );
        return $stmt->fetchAll();
    }

    private function canAccessThread(int $threadId, array $user): bool {
        if ($user['role'] === 'admin') return true;

        if ($user['role'] === 'parent') {
            // Historical thread ownership cannot override the child's current link.
            $stmt = $this->db->prepare(
                'SELECT t.id FROM parent_threads t
                 JOIN children c ON c.id = t.child_id
                 WHERE t.id = ? AND t.parent_user_id = ? AND c.parent_user_id = ? AND c.active = 1'
            );
            $stmt->execute([$threadId, $user['id'], $user['id']]);
            return (bool)$stmt->fetch();
        }

        // teacher — can access threads in their groups
        $stmt = $this->db->prepare(
            'SELECT t.id FROM parent_threads t
             JOIN teacher_groups tg ON tg.group_id = t.group_id AND tg.user_id = ?
             WHERE t.id = ?'
        );
        $stmt->execute([$user['id'], $threadId]);
        return (bool)$stmt->fetch();
    }

    private function parentOwnsChild(int $parentId, int $childId): bool {
        $stmt = $this->db->prepare(
            'SELECT id FROM children WHERE id=? AND parent_user_id=? AND active=1'
        );
        $stmt->execute([$childId, $parentId]);
        return (bool)$stmt->fetch();
    }

    private function findOrCreateThread(int $childId, int $parentId, int $groupId, string $subject): int {
        $stmt = $this->db->prepare(
            'SELECT id FROM parent_threads WHERE child_id=? AND parent_user_id=? AND group_id=? LIMIT 1'
        );
        $stmt->execute([$childId, $parentId, $groupId]);
        $existing = $stmt->fetchColumn();
        if ($existing) return (int)$existing;

        $this->db->prepare(
            'INSERT INTO parent_threads (child_id, parent_user_id, group_id, subject) VALUES (?,?,?,?)'
        )->execute([$childId, $parentId, $groupId, $subject]);

        return (int)$this->db->lastInsertId();
    }

    public function countUnread(array $user): int {
        try {
            if ($user['role'] === 'parent') {
                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM parent_thread_messages m
                     JOIN parent_threads t ON t.id = m.thread_id
                     JOIN children c ON c.id = t.child_id
                     WHERE t.parent_user_id=? AND c.parent_user_id=? AND c.active=1
                       AND m.sender_id<>? AND m.read_at IS NULL'
                );
                $stmt->execute([$user['id'], $user['id'], $user['id']]);
            } elseif ($user['role'] === 'teacher') {
                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM parent_thread_messages m
                     JOIN parent_threads t ON t.id = m.thread_id
                     JOIN teacher_groups tg ON tg.group_id = t.group_id AND tg.user_id = ?
                     WHERE m.sender_id <> ? AND m.read_at IS NULL'
                );
                $stmt->execute([$user['id'], $user['id']]);
            } else {
                $stmt = $this->db->query(
                    'SELECT COUNT(*) FROM parent_thread_messages WHERE read_at IS NULL'
                );
            }
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0; // table may not exist yet
        }
    }

    private function notifyTeachers(int $threadId, int $childId, int $groupId, string $parentName, string $body): void {
        // Find teacher emails for this group
        $stmt = $this->db->prepare(
            'SELECT u.email, u.name FROM users u
             JOIN teacher_groups tg ON tg.user_id = u.id
             WHERE tg.group_id = ? AND u.role = \'teacher\' AND u.active = 1'
        );
        $stmt->execute([$groupId]);
        $teachers = $stmt->fetchAll();
        if (empty($teachers)) return;

        // Child name
        $cStmt = $this->db->prepare('SELECT first_name, last_name FROM children WHERE id=?');
        $cStmt->execute([$childId]);
        $child = $cStmt->fetch();
        $childName = $child ? htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) : '';

        $inboxUrl = (defined('APP_URL') ? APP_URL : '') . (defined('BASE_URL') ? '' : '') . '/inbox';

        $subject  = 'Νέο μήνυμα από γονέα – ' . ($child ? $child['first_name'] . ' ' . $child['last_name'] : '');
        $htmlBody = '<p>Λάβατε νέο μήνυμα από τον/την <strong>' . htmlspecialchars($parentName) . '</strong>'
                  . ' για το παιδί <strong>' . $childName . '</strong>.</p>'
                  . '<blockquote style="border-left:3px solid #ccc;padding-left:12px;color:#555;">'
                  . nl2br(htmlspecialchars(mb_substr($body, 0, 300))) . '</blockquote>'
                  . '<p><a href="' . htmlspecialchars($inboxUrl) . '">Δείτε το μήνυμα στην εφαρμογή</a></p>';

        $mailer = new Mailer();
        foreach ($teachers as $teacher) {
            if (filter_var($teacher['email'], FILTER_VALIDATE_EMAIL)) {
                $mailer->send([$teacher['email']], $subject, $htmlBody);
            }
        }
    }

    private function notifyParent(int $threadId, string $teacherName, string $body): void {
        // Staff may reply to historical threads, but former parents must not be notified.
        $stmt = $this->db->prepare(
            'SELECT u.email, u.name, c.first_name, c.last_name
             FROM parent_threads t
             JOIN users u    ON u.id = t.parent_user_id
             JOIN children c ON c.id = t.child_id
             WHERE t.id = ? AND c.parent_user_id = t.parent_user_id AND c.active = 1
               AND u.active = 1 AND u.role = \'parent\''
        );
        $stmt->execute([$threadId]);
        $row = $stmt->fetch();
        if (!$row || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) return;

        $inboxUrl  = (defined('APP_URL') ? APP_URL : '') . '/inbox';
        $childName = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
        $subject   = 'Απάντηση δασκάλου – ' . $row['first_name'] . ' ' . $row['last_name'];
        $htmlBody  = '<p>Ο/Η δάσκαλος/α <strong>' . htmlspecialchars($teacherName) . '</strong>'
                   . ' απάντησε στο μήνυμά σας για το παιδί <strong>' . $childName . '</strong>.</p>'
                   . '<blockquote style="border-left:3px solid #ccc;padding-left:12px;color:#555;">'
                   . nl2br(htmlspecialchars(mb_substr($body, 0, 300))) . '</blockquote>'
                   . '<p><a href="' . htmlspecialchars($inboxUrl) . '">Δείτε το μήνυμα στην εφαρμογή</a></p>';

        $mailer = new Mailer();
        $mailer->send([$row['email']], $subject, $htmlBody);
    }
}
