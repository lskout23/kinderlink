<?php $csrf = (new Controller)->csrfToken(); ?>

<div class="section-box">
  <div class="section-title">Ορισμός Χρηστών</div>
  <div class="section-body" style="padding:0;">
    <div style="overflow-x:auto;">
      <table class="data-table" id="users-table">
        <thead>
          <tr>
            <th>Username</th>
            <th>Ονοματεπώνυμο</th>
            <th>Email</th>
            <th style="width:90px;">Ρόλος</th>
            <th>Τμήματα (Teacher)</th>
            <th style="width:90px;text-align:center;">Ενεργός</th>
            <th style="width:290px;text-align:center;">Ενέργειες</th>
          </tr>
        </thead>
        <tbody id="users-tbody">
          <tr><td colspan="7" class="text-center text-muted">Φόρτωση...</td></tr>
        </tbody>
      </table>
    </div>
    <div class="table-toolbar">
      <button class="btn-tool" onclick="openUserModal(0)">➕ Προσθήκη</button>
      <span class="pagination-info" id="users-pagination-info"></span>
      <button class="btn-tool" onclick="usersPage(-1)">◀</button>
      <span id="users-page-indicator" style="font-size:11px;"></span>
      <button class="btn-tool" onclick="usersPage(1)">▶</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="user-modal">
  <div class="modal-box" style="max-width:620px;">
    <div class="modal-header">
      <span id="user-modal-title">Προσθήκη Χρήστη</span>
      <button class="modal-close" onclick="closeUserModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="user-form" autocomplete="off">
        <input type="hidden" id="uf-id" name="id" value="0">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <input type="text" name="fake_username" autocomplete="section-login username" tabindex="-1" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
        <input type="password" name="fake_password" autocomplete="section-login current-password" tabindex="-1" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">

        <div class="form-group">
          <label>Ονοματεπώνυμο <span class="required">*</span></label>
          <input type="text" id="uf-name" name="name" required maxlength="200" style="min-width:260px;">
        </div>

        <div class="form-group">
          <label>Username <span class="required">*</span></label>
          <input type="text" id="uf-username" name="username" required minlength="4" maxlength="50" title="4-50 χαρακτήρες: λατινικά, αριθμοί, @, τελεία, _ ή -" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true">
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" id="uf-email" name="email" maxlength="255" autocomplete="email">
        </div>

        <div class="form-group">
          <label>Ρόλος</label>
          <select id="uf-role" name="role" onchange="toggleRoleSections()">
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>
            <option value="parent">Parent</option>
          </select>
        </div>

        <div class="form-group" id="teacher-groups-wrap">
          <label>Ανάθεση Τμημάτων (Teacher)</label>
          <div style="max-height:140px;overflow-y:auto;border:1px solid #ddd;padding:8px;border-radius:4px;">
            <?php foreach ($groups as $g): ?>
            <label style="display:block;margin:2px 0;">
              <input type="checkbox" class="uf-group" value="<?= (int)$g['id'] ?>">
              <?= htmlspecialchars($g['name']) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label id="uf-password-label">Κωδικός <span class="required">*</span></label>
          <input type="password" id="uf-password" name="password" minlength="10" autocomplete="new-password">
          <small class="text-muted" id="uf-password-help">Για νέο χρήστη είναι υποχρεωτικός (10+ χαρακτήρες, κεφαλαίο, μικρό, αριθμός, σύμβολο).</small>
        </div>

        <div class="form-group">
          <label>
            <input type="checkbox" id="uf-active" name="active" checked>
            Ενεργός λογαριασμός
          </label>
        </div>
      </form>
      <div id="user-form-error" class="alert alert-danger hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-accent" onclick="saveUser()">💾 Αποθήκευση</button>
      <button class="btn btn-primary" onclick="closeUserModal()">Ακύρωση</button>
    </div>
  </div>
</div>

<script>
var usersCurrentPage = 1, usersTotalPages = 1;
var usersCache = [];
var USERNAME_POLICY_MSG = 'Περιορισμοί username: 4-50 χαρακτήρες, μόνο λατινικά γράμματα, αριθμοί, @, τελεία (.), κάτω παύλα (_) ή παύλα (-).';
var PASSWORD_POLICY_MSG = 'Περιορισμοί κωδικού: τουλάχιστον 10 χαρακτήρες, τουλάχιστον 1 μικρό, 1 κεφαλαίο, 1 αριθμός, 1 σύμβολο, χωρίς κενά.';

function markUsernameTouched() {
  var usernameEl = document.getElementById('uf-username');
  if (usernameEl) usernameEl.setAttribute('data-touched', '1');
}

function loadUsers(page) {
  page = page || usersCurrentPage;
  usersCurrentPage = page;

  document.getElementById('users-tbody').innerHTML =
    '<tr><td colspan="7" class="text-center text-muted">Φόρτωση...</td></tr>';

  apiPost('/api/users', {page: page, page_size: 20}, function(err, resp) {
    if (err || !resp) { showToast('Σφάλμα φόρτωσης.', 'danger'); return; }

    usersCache = resp.rows || [];
    usersTotalPages = Math.max(1, Math.ceil((resp.total || 0) / 20));
    document.getElementById('users-pagination-info').textContent = '1 - ' + usersCache.length + ' από ' + (resp.total || 0);
    document.getElementById('users-page-indicator').textContent = 'Σελ ' + page + ' από ' + usersTotalPages;

    var tbody = '';
    usersCache.forEach(function(r) {
      var roleLabel = r.role === 'admin' ? 'Admin' : (r.role === 'teacher' ? 'Teacher' : 'Parent');
      tbody += '<tr>' +
        '<td>' + esc(r.username) + '</td>' +
        '<td>' + esc(r.name || '') + '</td>' +
        '<td>' + esc(r.email || '') + '</td>' +
        '<td>' + roleLabel + '</td>' +
        '<td>' + esc(r.teacher_groups || '-') + '</td>' +
        '<td class="text-center">' + (String(r.active) === '1' ? '☑' : '☐') + '</td>' +
        '<td class="text-center">' +
          '<a href="#" class="btn btn-sm btn-primary" onclick="openUserModal(' + r.id + ')">✏️ Επεξεργασία</a> ' +
          '<a href="#" class="btn btn-sm ' + (String(r.active) === '1' ? 'btn-danger' : 'btn-accent') + '" onclick="toggleUserActive(' + r.id + ',' + (String(r.active) === '1' ? '0' : '1') + ')">' + (String(r.active) === '1' ? '⛔ Απενεργ.' : '✅ Ενεργοπ.') + '</a> ' +
          '<a href="#" class="btn btn-sm btn-danger" onclick="deleteUser(' + r.id + ')">🗑️ Διαγραφή</a>' +
        '</td>' +
      '</tr>';
    });

    if (!usersCache.length) tbody = '<tr><td colspan="7" class="text-center text-muted">Δεν υπάρχουν εγγραφές</td></tr>';
    document.getElementById('users-tbody').innerHTML = tbody;
  });
}

function usersPage(dir) {
  var next = usersCurrentPage + dir;
  if (next < 1 || next > usersTotalPages) return;
  loadUsers(next);
}

function openUserModal(id) {
  var form = document.getElementById('user-form');
  var usernameEl = document.getElementById('uf-username');
  usernameEl.setAttribute('data-touched', '0');
  usernameEl.removeEventListener('input', markUsernameTouched);
  usernameEl.removeEventListener('change', markUsernameTouched);
  usernameEl.addEventListener('input', markUsernameTouched);
  usernameEl.addEventListener('change', markUsernameTouched);
  form.reset();
  document.getElementById('user-form-error').classList.add('hidden');
  document.getElementById('uf-id').value = id;

  document.querySelectorAll('.uf-group').forEach(function(cb){ cb.checked = false; });

  if (id > 0) {
    var u = usersCache.find(function(x){ return Number(x.id) === Number(id); });
    document.getElementById('user-modal-title').textContent = 'Επεξεργασία Χρήστη';
    document.getElementById('uf-password-label').innerHTML = 'Νέος Κωδικός';
    document.getElementById('uf-password-help').textContent = 'Αφήστε κενό για να μη γίνει αλλαγή.';

    if (u) {
      document.getElementById('uf-name').value = u.name || '';
      document.getElementById('uf-username').value = u.username || '';
      document.getElementById('uf-email').value = u.email || '';
      document.getElementById('uf-username').setAttribute('data-expected', u.username || '');
      document.getElementById('uf-role').value = u.role || 'teacher';
      document.getElementById('uf-active').checked = String(u.active) === '1';
    }

    apiPost('/api/users/groups', {user_id:id}, function(err, resp) {
      if (!err && resp && Array.isArray(resp.group_ids)) {
        var idSet = {};
        resp.group_ids.forEach(function(gid){ idSet[String(gid)] = true; });
        document.querySelectorAll('.uf-group').forEach(function(cb) {
          cb.checked = !!idSet[String(cb.value)];
        });
      }
      toggleRoleSections();
    });
  } else {
    document.getElementById('user-modal-title').textContent = 'Προσθήκη Χρήστη';
    document.getElementById('uf-password-label').innerHTML = 'Κωδικός <span class="required">*</span>';
    document.getElementById('uf-password-help').textContent = 'Για νέο χρήστη είναι υποχρεωτικός.';
    document.getElementById('uf-role').value = 'teacher';
    document.getElementById('uf-active').checked = true;
    document.getElementById('uf-username').setAttribute('data-expected', '');
    toggleRoleSections();
  }

  document.getElementById('user-modal').classList.add('show');
  protectUsernameFromAutofill();
}

function protectUsernameFromAutofill() {
  var usernameEl = document.getElementById('uf-username');
  var emailEl = document.getElementById('uf-email');
  if (!usernameEl || !emailEl) return;

  var expected = usernameEl.getAttribute('data-expected') || '';
  if (!expected || expected.indexOf('@') !== -1) return;

  // Some managers overwrite username shortly after modal render.
  [80, 220, 500].forEach(function(delay) {
    window.setTimeout(function() {
      if (document.activeElement === usernameEl) return;
      if (usernameEl.getAttribute('data-touched') === '1') return;
      var uname = (usernameEl.value || '').trim();
      var email = (emailEl.value || '').trim();
      if (uname && email && uname === email && uname.indexOf('@') !== -1) {
        usernameEl.value = expected;
      }
    }, delay);
  });
}

function closeUserModal() {
  document.getElementById('user-modal').classList.remove('show');
}

function toggleRoleSections() {
  var role = document.getElementById('uf-role').value;
  document.getElementById('teacher-groups-wrap').style.display = role === 'teacher' ? '' : 'none';
}

function saveUser() {
  var form = document.getElementById('user-form');
  var username = (document.getElementById('uf-username').value || '').trim();
  var password = document.getElementById('uf-password').value || '';
  var id = Number(document.getElementById('uf-id').value || 0);

  if (!/^[A-Za-z0-9@._-]{4,50}$/.test(username)) {
    var uErr = document.getElementById('user-form-error');
    uErr.textContent = 'Μη έγκυρο username. ' + USERNAME_POLICY_MSG;
    uErr.classList.remove('hidden');
    return;
  }

  var mustValidatePassword = (id === 0) || password.length > 0;
  if (mustValidatePassword) {
    var strongPassword = password.length >= 10
      && !/\s/.test(password)
      && /[a-z]/.test(password)
      && /[A-Z]/.test(password)
      && /\d/.test(password)
      && /[^A-Za-z0-9]/.test(password);

    if (!strongPassword) {
      var pErr = document.getElementById('user-form-error');
      pErr.textContent = 'Μη ασφαλής κωδικός. ' + PASSWORD_POLICY_MSG;
      pErr.classList.remove('hidden');
      return;
    }
  }

  if (!form.reportValidity()) return;

  var data = {};
  new FormData(form).forEach(function(v, k) { data[k] = v; });

  document.getElementById('user-form-error').classList.add('hidden');
  data.active = document.getElementById('uf-active').checked ? '1' : '0';
  data.group_ids = [];
  if (document.getElementById('uf-role').value === 'teacher') {
    document.querySelectorAll('.uf-group:checked').forEach(function(cb){ data.group_ids.push(cb.value); });
  }

  if (id > 0 && !document.getElementById('uf-password').value) {
    delete data.password;
  }

  apiPost('/api/users/save', data, function(err, resp) {
    if (err || (resp && resp.error)) {
      var el = document.getElementById('user-form-error');
      el.textContent = (resp && resp.error) ? resp.error : 'Σφάλμα αποθήκευσης.';
      el.classList.remove('hidden');
      return;
    }

    closeUserModal();
    loadUsers(usersCurrentPage);
    showToast('Ο χρήστης αποθηκεύτηκε.', 'success');
  });
}

async function toggleUserActive(id, active) {
  var txt = active === 1 ? 'Να ενεργοποιηθεί ο χρήστης;' : 'Να απενεργοποιηθεί ο χρήστης;';
  if (!await appConfirm(txt, {
    title: active === 1 ? 'Ενεργοποίηση χρήστη' : 'Απενεργοποίηση χρήστη',
    confirmLabel: active === 1 ? 'Ενεργοποίηση' : 'Απενεργοποίηση'
  })) return;

  apiPost('/api/users/set-active', {id:id, active: active ? '1' : ''}, function(err, resp) {
    if (err || (resp && resp.error)) {
      showToast((resp && resp.error) ? resp.error : 'Σφάλμα ενημέρωσης.', 'danger');
      return;
    }
    loadUsers(usersCurrentPage);
    showToast('Η κατάσταση ενημερώθηκε.', 'success');
  });
}

async function deleteUser(id) {
  if (!await confirmDelete('Θέλετε σίγουρα να διαγράψετε αυτόν τον χρήστη;')) return;

  apiPost('/api/users/delete', {id:id, _token:CSRF_TOKEN}, function(err, resp) {
    if (err || (resp && resp.error)) {
      showToast((resp && resp.error) ? resp.error : 'Σφάλμα διαγραφής.', 'danger');
      return;
    }
    loadUsers(usersCurrentPage);
    showToast('Ο χρήστης διαγράφηκε.', 'success');
  });
}

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str == null ? '' : String(str))); return d.innerHTML; }
loadUsers(1);
</script>
