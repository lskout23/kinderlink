<?php
$isParent  = ($user['role'] === 'parent');
$isTeacher = ($user['role'] === 'teacher');
$isAdmin   = ($user['role'] === 'admin');
?>

<div class="inbox-layout">

  <!-- ── Left panel: thread list ── -->
  <div class="inbox-threads">
    <div class="section-box">
      <div class="section-title" style="display:flex;align-items:center;justify-content:space-between;">
        <span>💬 Συνομιλίες</span>
        <?php if ($isParent): ?>
        <button class="btn btn-primary btn-sm" onclick="openNewThreadModal()">+ Νέο μήνυμα</button>
        <?php endif; ?>
      </div>
      <div class="section-body" style="padding:0;">
        <div id="thread-list" style="min-height:60px;">
          <div class="text-center text-muted" style="padding:16px;">Φόρτωση...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Right panel: conversation ── -->
  <div class="inbox-conversation">
    <div class="section-box" id="conversation-box" style="display:none;">
      <div class="section-title" id="conversation-title">Συνομιλία</div>
      <div class="section-body">
        <div id="conversation-messages" style="display:flex;flex-direction:column;gap:10px;max-height:420px;overflow-y:auto;padding-bottom:8px;"></div>
        <hr style="margin:12px 0;">
        <div>
          <textarea id="reply-body" rows="3" aria-label="Απάντηση στη συνομιλία" style="width:100%;box-sizing:border-box;resize:vertical;" placeholder="Γράψτε την απάντησή σας..."></textarea>
          <div style="display:flex;justify-content:flex-end;margin-top:6px;">
            <button class="btn btn-primary" onclick="sendReply()">📤 Αποστολή</button>
          </div>
        </div>
      </div>
    </div>
    <div id="conversation-empty" class="section-box">
      <div class="section-body text-center text-muted" style="padding:32px;">
        Επιλέξτε μια συνομιλία από αριστερά για να τη δείτε.
      </div>
    </div>
  </div>

</div>

<!-- ── New Thread Modal (parent only) ── -->
<?php if ($isParent): ?>
<div id="new-thread-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:8px;padding:24px;width:min(460px,94vw);box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <div style="font-weight:700;font-size:15px;margin-bottom:16px;">Νέο μήνυμα προς δάσκαλο/α</div>

    <div style="margin-bottom:10px;">
      <label style="font-size:12px;display:block;margin-bottom:4px;">Παιδί / Τμήμα</label>
      <select id="nt-child-group" style="width:100%;">
        <option value="">-- Επιλέξτε --</option>
      </select>
    </div>
    <div style="margin-bottom:10px;">
      <label style="font-size:12px;display:block;margin-bottom:4px;">Θέμα (προαιρετικό)</label>
      <input type="text" id="nt-subject" placeholder="π.χ. Ερώτηση για σήμερα" style="width:100%;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:14px;">
      <label style="font-size:12px;display:block;margin-bottom:4px;">Μήνυμα <span style="color:#e74c3c;">*</span></label>
      <textarea id="nt-body" rows="4" style="width:100%;box-sizing:border-box;resize:vertical;" maxlength="2000" placeholder="Γράψτε το μήνυμά σας..."></textarea>
      <div style="text-align:right;font-size:11px;color:#999;margin-top:2px;"><span id="nt-chars">0</span>/2000</div>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button class="btn btn-secondary" onclick="closeNewThreadModal()">Ακύρωση</button>
      <button class="btn btn-primary" onclick="submitNewThread()">📤 Αποστολή</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
var currentThreadId = null;
var isParent = <?= $isParent ? 'true' : 'false' ?>;

// ── Load thread list ─────────────────────────────────────────────────
function loadThreadList() {
  apiPost('/api/inbox/thread-list', {}, function(err, resp) {
    if (err || !resp.threads) { document.getElementById('thread-list').innerHTML = '<div class="text-muted" style="padding:12px;">Σφάλμα φόρτωσης.</div>'; return; }
    var html = '';
    if (!resp.threads.length) {
      html = '<div class="text-muted" style="padding:16px;text-align:center;">Δεν υπάρχουν συνομιλίες ακόμα.</div>';
    }
    resp.threads.forEach(function(t) {
      var unread = parseInt(t.unread) > 0;
      var preview = (t.last_body || '').substring(0, 60) + ((t.last_body || '').length > 60 ? '…' : '');
      var who = isParent ? esc(t.group_name) : esc((t.parent_name || '') + ' – ' + t.first_name + ' ' + t.last_name);
      html += '<div class="thread-item' + (unread ? ' thread-unread' : '') + '" onclick="openThread(' + t.id + ')" '
            + 'style="padding:12px 14px;cursor:pointer;border-bottom:1px solid #eee;">'
            + '<div style="display:flex;justify-content:space-between;align-items:center;">'
            + '<span style="font-weight:' + (unread ? '700' : '500') + ';font-size:13px;">' + esc(t.first_name + ' ' + t.last_name) + '</span>'
            + (unread ? '<span style="background:#e74c3c;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;">' + t.unread + '</span>' : '')
            + '</div>'
            + '<div style="font-size:11px;color:#666;margin-top:2px;">' + who + '</div>'
            + '<div style="font-size:11px;color:#999;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(preview) + '</div>'
            + '</div>';
    });
    document.getElementById('thread-list').innerHTML = html || '<div class="text-muted" style="padding:16px;text-align:center;">Δεν υπάρχουν συνομιλίες ακόμα.</div>';
  });
}

// ── Open a thread ────────────────────────────────────────────────────
function openThread(threadId) {
  currentThreadId = threadId;
  document.getElementById('conversation-box').style.display  = 'block';
  document.getElementById('conversation-empty').style.display = 'none';
  document.getElementById('conversation-messages').innerHTML = '<div class="text-muted text-center">Φόρτωση...</div>';

  apiPost('/api/inbox/thread-messages', {thread_id: threadId}, function(err, resp) {
    if (err || !resp.messages) { showToast('Σφάλμα φόρτωσης μηνυμάτων.', 'danger'); return; }

    var t = resp.thread;
    document.getElementById('conversation-title').textContent =
      (t.first_name + ' ' + t.last_name) + ' — ' + t.group_name + (t.subject ? ' — ' + t.subject : '');

    var html = '';
    var myUserId = <?= json_encode((string)($user['id'] ?? '')) ?>;
    var isAdmin  = <?= $isAdmin ? 'true' : 'false' ?>;
    resp.messages.forEach(function(m) {
      var isMine = (String(m.sender_id) === String(myUserId));
      var canDel = isMine || isAdmin;
      html += '<div style="align-self:' + (isMine ? 'flex-end' : 'flex-start') + ';max-width:80%;">' 
            + '<div style="background:' + (isMine ? '#d1fae5' : '#f3f4f6') + ';border-radius:10px;padding:10px 14px;font-size:13px;position:relative;">'
            + '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:4px;">'
            + '<span style="font-weight:600;font-size:11px;color:#555;">' + esc(m.sender_name) + ' · ' + esc(m.created_at) + '</span>'
            + (canDel ? '<button onclick="deleteMessage(' + m.id + ')" style="background:none;border:none;cursor:pointer;font-size:13px;color:#aaa;padding:0;line-height:1;" title="Διαγραφή μηνύματος">🗑️</button>' : '')
            + '</div>'
            + nl2br(esc(m.body))
            + '</div></div>';
    });
    document.getElementById('conversation-messages').innerHTML = html;

    // Scroll to bottom
    var box = document.getElementById('conversation-messages');
    box.scrollTop = box.scrollHeight;

    // Refresh thread list to clear unread badge
    loadThreadList();
  });
}

// ── Delete message ──────────────────────────────────────────────────
var inboxDeletePending = false;
async function deleteMessage(msgId) {
  if (inboxDeletePending) return;
  var threadId = currentThreadId;
  var data = {msg_id: msgId};
  inboxDeletePending = true;
  try {
    if (!await confirmDelete()) return;
    var result = await new Promise(function(resolve) {
      apiPost('/api/inbox/delete-message', data, function(err, resp) { resolve({err:err,resp:resp}); });
    });
    var err = result.err, resp = result.resp;
    if (err || !resp || resp.error) { showToast((resp && resp.error) || 'Σφάλμα.', 'danger'); return; }
    if (resp.thread_deleted) {
      // Whole thread was deleted (no messages left)
      if (currentThreadId === threadId) {
        document.getElementById('conversation-box').style.display   = 'none';
        document.getElementById('conversation-empty').style.display = 'block';
        currentThreadId = null;
      }
      showToast('Η συνομιλία διαγράφηκε.', 'success');
    } else {
      if (currentThreadId === threadId) openThread(threadId);
      showToast('Το μήνυμα διαγράφηκε.', 'success');
    }
    loadThreadList();
  } catch (error) {
    showToast('Σφάλμα.', 'danger');
  } finally {
    inboxDeletePending = false;
  }
}

// ── Reply ────────────────────────────────────────────────────────────
function sendReply() {
  if (!currentThreadId) return;
  var body = document.getElementById('reply-body').value.trim();
  if (!body) { showToast('Γράψτε ένα μήνυμα.', 'warning'); return; }

  apiPost('/api/inbox/reply', {thread_id: currentThreadId, body: body}, function(err, resp) {
    if (err || resp.error) { showToast(resp.error || 'Σφάλμα.', 'danger'); return; }
    document.getElementById('reply-body').value = '';
    openThread(currentThreadId);
    showToast('Το μήνυμα στάλθηκε.', 'success');
  });
}

// ── New Thread (parent) ──────────────────────────────────────────────
<?php if ($isParent): ?>
var childGroupOptions = [];

function openNewThreadModal() {
  var modal = document.getElementById('new-thread-modal');
  modal.style.display = 'flex';

  if (!childGroupOptions.length) {
    apiPost('/api/inbox/children', {}, function(err, resp) {
      if (err || !resp.children) return;
      childGroupOptions = resp.children;
      var sel = document.getElementById('nt-child-group');
      sel.innerHTML = '<option value="">-- Επιλέξτε --</option>';
      resp.children.forEach(function(c) {
        sel.innerHTML += '<option value="' + c.child_id + ':' + c.group_id + '">'
          + esc(c.first_name + ' ' + c.last_name) + ' (' + esc(c.group_name) + ')'
          + (c.teacher_name ? ' – ' + esc(c.teacher_name) : '') + '</option>';
      });
    });
  }
}

function closeNewThreadModal() {
  document.getElementById('new-thread-modal').style.display = 'none';
}

document.getElementById('nt-body').addEventListener('input', function() {
  document.getElementById('nt-chars').textContent = this.value.length;
});

function submitNewThread() {
  var sel     = document.getElementById('nt-child-group').value;
  var subject = document.getElementById('nt-subject').value.trim();
  var body    = document.getElementById('nt-body').value.trim();

  if (!sel)  { showToast('Επιλέξτε παιδί/τμήμα.', 'warning'); return; }
  if (!body) { showToast('Γράψτε το μήνυμά σας.',  'warning'); return; }

  var parts   = sel.split(':');
  var childId = parts[0];
  var groupId = parts[1];

  apiPost('/api/inbox/send', {child_id: childId, group_id: groupId, subject: subject, body: body}, function(err, resp) {
    if (err || resp.error) { showToast(resp.error || 'Σφάλμα.', 'danger'); return; }
    closeNewThreadModal();
    document.getElementById('nt-body').value    = '';
    document.getElementById('nt-subject').value = '';
    document.getElementById('nt-chars').textContent = '0';
    showToast('Το μήνυμα στάλθηκε!', 'success');
    loadThreadList();
    openThread(resp.thread_id);
  });
}

// Close modal on background click
document.getElementById('new-thread-modal').addEventListener('click', function(e) {
  if (e.target === this) closeNewThreadModal();
});
<?php endif; ?>

// ── Helpers ──────────────────────────────────────────────────────────
function esc(str) { var d = document.createElement('div'); d.appendChild(document.createTextNode(String(str||''))); return d.innerHTML; }
function nl2br(str) { return str.replace(/\n/g, '<br>'); }

// ── Init ─────────────────────────────────────────────────────────────
loadThreadList();
</script>

<style>
.thread-item:hover { background: #f9fafb; }
.thread-unread     { background: #f0fdf4; }
</style>
