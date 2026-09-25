<?php $csrf = (new Controller)->csrfToken(); ?>

<div class="section-box">
  <div class="section-title section-title-icon"><span data-icon="edit" aria-hidden="true"></span>Δημιουργία Μηνυμάτων</div>
  <div class="section-body">

    <!-- Toolbar -->
    <div class="filter-bar compose-toolbar" style="align-items:flex-end;gap:16px;flex-wrap:wrap;">
      <div>
        <label for="sel-group" style="font-size:11px;display:block;margin-bottom:2px;">Τμήμα</label>
        <select id="sel-group" style="min-width:180px;">
          <option value="">-- Επιλογή Τμήματος --</option>
          <?php foreach ($groups as $g): ?>
          <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="sel-date" style="font-size:11px;display:block;margin-bottom:2px;">Ημερομηνία</label>
        <input type="date" id="sel-date" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="compose-actions">
        <button class="btn btn-primary" id="btn-load-children" onclick="loadChildren()">🔍 Φόρτωση</button>
        <button class="btn btn-accent"  id="btn-save-all"  onclick="saveAll()" disabled>💾 Αποθήκευση</button>
        <button class="btn btn-send-email" id="btn-send-email" onclick="sendEmails()" disabled>📧 Αποστολή Email</button>
        <button class="btn btn-secondary" id="btn-preview-email" onclick="openEmailPreview()" disabled>👁️ Προεπισκόπηση</button>
        <button class="btn btn-danger-outline" id="btn-clear-all" onclick="clearAll()" disabled>🗑 Καθαρισμός</button>
      </div>
    </div>

    <p id="attendance-notice" role="status" style="margin-top:12px;"></p>
    <!-- Children table -->
    <div style="overflow-x:auto;margin-top:12px;" id="children-area">
      <div class="text-muted text-center" style="padding:20px;">Επιλέξτε τμήμα και ημερομηνία.</div>
    </div>

  </div>
</div>

<!-- BULK ACTIVITY PANELS -->
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:12px;" id="activity-panels">

  <div class="section-box" style="flex:1;min-width:240px;">
    <div class="section-title section-title-icon"><span data-icon="list" aria-hidden="true"></span>Δραστηριότητες (προσθ/αντ)</div>
    <div class="section-body" style="padding:8px;">
      <div style="max-height:200px;overflow-y:auto;" id="acts-both-list">
        <?php foreach ($actBoth as $a): ?>
        <label style="display:block;font-size:12px;padding:2px 0;">
          <input type="checkbox" class="act-both-cb" value="<?= htmlspecialchars($a['name']) ?>">
          <?= htmlspecialchars($a['name']) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:8px;display:flex;gap:6px;">
        <button class="btn-tool" onclick="bulkApply('both','replace')">Αντικατάσταση</button>
        <button class="btn-tool" onclick="bulkApply('both','add')">Πρόσθεση</button>
      </div>
    </div>
  </div>

  <div class="section-box" style="flex:1;min-width:240px;">
    <div class="section-title section-title-icon"><span data-icon="mail" aria-hidden="true"></span>Παρατηρήσεις (email μόνο)</div>
    <div class="section-body" style="padding:8px;">
      <div style="max-height:200px;overflow-y:auto;" id="acts-email-list">
        <?php foreach ($actEmailOnly as $a): ?>
        <label style="display:block;font-size:12px;padding:2px 0;">
          <input type="checkbox" class="act-email-cb" value="<?= htmlspecialchars($a['name']) ?>">
          <?= htmlspecialchars($a['name']) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:8px;display:flex;gap:6px;">
        <button class="btn-tool" onclick="bulkApply('email','replace')">Αντικατάσταση</button>
        <button class="btn-tool" onclick="bulkApply('email','add')">Πρόσθεση</button>
      </div>
    </div>
  </div>

</div>

<!-- PHOTO UPLOAD PANEL -->
<div class="section-box" style="margin-top:12px;">
  <div class="section-title section-title-icon"><span data-icon="camera" aria-hidden="true"></span>Φωτογραφίες (έως 5MB ανά εικόνα)</div>
  <div class="section-body">
    <div class="filter-bar photo-panel-bar">
      <div class="photo-panel-inputs">
        <div class="photo-panel-field photo-panel-child">
          <label style="font-size:11px;display:block;margin-bottom:2px;">Παιδί</label>
          <div class="photo-panel-stack">
            <input type="text" id="photo-child-search" placeholder="Αναζήτηση παιδιού..." oninput="filterPhotoChildren()" autocomplete="off">
            <select id="photo-child">
              <option value="">-- Επιλέξτε παιδί --</option>
            </select>
          </div>
        </div>
        <div class="photo-panel-field photo-panel-child" style="min-width:280px;">
          <label style="font-size:11px;display:block;margin-bottom:2px;">Επιλογή Παιδιών για Μαζική Μεταφόρτωση</label>
          <div class="photo-panel-stack">
            <input type="text" id="photo-group-child-search" placeholder="Φίλτρο επιλεγμένων παιδιών..." oninput="filterGroupTargetChildren()" autocomplete="off">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
              <button class="btn btn-sm" type="button" onclick="selectAllGroupTargetChildren(true)">Επιλογή Όλων</button>
              <button class="btn btn-sm" type="button" onclick="selectAllGroupTargetChildren(false)">Καθαρισμός</button>
              <span id="photo-group-selected-count" class="text-muted" style="font-size:12px;">0 επιλεγμένα</span>
            </div>
            <div id="photo-group-children-list" style="max-height:130px;overflow:auto;border:1px solid #d1d5db;border-radius:6px;padding:6px;background:#fff;"></div>
          </div>
        </div>
        <div class="photo-panel-field photo-panel-files">
          <label style="font-size:11px;display:block;margin-bottom:2px;">Φωτογραφίες</label>
          <input type="file" id="photo-files" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
        </div>
        <div class="photo-panel-action photo-panel-upload">
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:flex-start;">
            <select id="photo-upload-mode" onchange="onPhotoUploadModeChange()" style="min-width:220px;">
              <option value="single" selected>Μεταφόρτωση ανά παιδί</option>
              <option value="selected">Μεταφόρτωση σε επιλεγμένα παιδιά</option>
            </select>
            <button
              id="btn-photo-upload-main"
              class="btn btn-primary"
              type="button"
              onclick="uploadPhotosByMode()"
              title="Μεταφόρτωση"
            >⬆ Μεταφόρτωση</button>
          </div>
        </div>
      </div>

      <div class="photo-panel-filters">
        <div class="photo-panel-field photo-panel-sort">
          <label style="font-size:11px;display:block;margin-bottom:2px;">Ταξινόμηση</label>
          <select id="photo-sort" onchange="loadPhotoList()">
            <option value="size_desc" selected>Μεγαλύτερες πρώτες</option>
            <option value="size_asc">Μικρότερες πρώτες</option>
            <option value="name_asc">Όνομα Α-Ω</option>
            <option value="name_desc">Όνομα Ω-Α</option>
            <option value="newest">Νεότερες πρώτες</option>
            <option value="oldest">Παλιότερες πρώτες</option>
          </select>
        </div>
        <div class="photo-panel-toggle-group">
          <div id="photo-show-hidden-wrap" class="photo-panel-toggle" style="display:none;">
            <label style="font-size:12px;display:flex;align-items:center;gap:6px;white-space:nowrap;">
              <input type="checkbox" id="photo-show-hidden" onchange="loadPhotoList()"> Εμφάνιση κρυφών
            </label>
          </div>
          <div class="photo-panel-toggle">
            <label style="font-size:12px;display:flex;align-items:center;gap:6px;white-space:nowrap;">
              <input type="checkbox" id="photo-all-dates" onchange="loadPhotoList()"> Όλες οι ημερομηνίες
            </label>
          </div>
        </div>
      </div>

      <div class="photo-panel-danger-row">
        <div class="photo-panel-bulk-actions">
          <button class="btn" type="button" onclick="bulkHideSelectedPhotos()">Απόκρυψη Επιλεγμένων</button>
          <button class="btn" style="background:#7a0f0f;color:#fff;" type="button" onclick="bulkPurgeSelectedPhotos()">Οριστική Διαγραφή Επιλεγμένων</button>
          <button class="btn" id="btn-photo-purge-day" style="display:none;background:#7a0f0f;color:#fff;" type="button" onclick="purgePhotosDay()">🧨 Οριστική Διαγραφή Ημέρας</button>
        </div>
      </div>
    </div>

    <div id="photo-storage-alert" class="alert alert-warning" style="display:none;margin-top:10px;"></div>
    <div id="photo-upload-feedback" class="alert" style="display:none;margin-top:10px;"></div>
    <div id="photo-dup-remember-banner" class="alert alert-info" style="display:none;margin-top:10px;"></div>
    <div id="photo-list" class="text-muted" style="padding:8px 2px;">Επιλέξτε παιδί για να δείτε/ανεβάσετε φωτογραφίες.</div>

<!-- EMAIL PREVIEW MODAL -->
<div id="email-preview-modal" class="modal-overlay" style="display:none;z-index:5000;">
  <div class="modal-box" style="max-width:720px;width:calc(100% - 24px);max-height:90vh;display:flex;flex-direction:column;">
    <div class="modal-header">
      <span>Προεπισκόπηση Email</span>
      <button class="modal-close" onclick="closeEmailPreview()">✕</button>
    </div>
    <div style="padding:8px 16px;background:#f8fafc;border-bottom:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
      <label style="margin-right:12px;">Παιδί:</label>
      <select id="preview-child-select" onchange="loadEmailPreview()" style="font-size:12px;"></select>
    </div>
    <div id="email-preview-body" style="flex:1;overflow-y:auto;padding:16px;background:#fff;">
      <div class="text-muted text-center">Φόρτωση...</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary" onclick="closeEmailPreview()">Κλείσιμο</button>
    </div>
  </div>
</div>

    <div id="photo-dup-modal" class="modal-overlay" style="display:none;z-index:4000;">
      <div class="modal-box" style="max-width:560px;width:calc(100% - 24px);">
        <div class="modal-header">
          <span>Βρέθηκαν Διπλότυπες Φωτογραφίες</span>
          <button class="modal-close" type="button" onclick="closePhotoDupDecisionModal('cancel')">✕</button>
        </div>
        <div class="modal-body">
          <div style="font-size:13px;color:#333;line-height:1.45;">
            Υπάρχουν ήδη φωτογραφίες με ίδιο όνομα στον server.
          </div>
          <div id="photo-dup-modal-summary" class="text-muted" style="margin-top:6px;font-size:12px;"></div>
          <div id="photo-dup-modal-list" style="margin-top:8px;max-height:180px;overflow:auto;border:1px solid #e4e7eb;border-radius:6px;padding:8px;background:#fafafa;font-size:12px;"></div>
          <div class="text-muted" style="margin-top:8px;font-size:12px;line-height:1.45;">
            Επιλέξτε τι θέλετε να γίνει:
          </div>
          <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:12px;color:#2d3748;">
            <input type="checkbox" id="photo-dup-modal-remember" value="1"> Διατήρηση της επιλογής μου για αυτή τη συνεδρία
          </label>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
          <button class="btn btn-primary" type="button" onclick="closePhotoDupDecisionModal('replace')">Αντικατάσταση Διπλότυπων</button>
          <button class="btn" type="button" onclick="closePhotoDupDecisionModal('skip')">Παράλειψη Διπλότυπων</button>
          <button class="btn" type="button" onclick="closePhotoDupDecisionModal('cancel')">Ακύρωση</button>
        </div>
      </div>
    </div>

    <dialog id="photo-clear-day-modal" class="clear-form-dialog" aria-labelledby="photo-clear-day-title" aria-describedby="photo-clear-day-description photo-clear-day-warning" oncancel="event.preventDefault(); closePhotoClearDayModal(false)">
      <div class="clear-form-heading">
        <span class="clear-form-symbol" data-icon="trash" aria-hidden="true"></span>
        <button class="icon-button clear-form-close" type="button" aria-label="Διατήρηση φωτογραφιών" onclick="closePhotoClearDayModal(false)"><span data-icon="close" aria-hidden="true"></span></button>
      </div>
      <h2 id="photo-clear-day-title">Οριστική διαγραφή φωτογραφιών;</h2>
      <p id="photo-clear-day-context" class="clear-form-context"></p>
      <p id="photo-clear-day-description">Τα πεδία καθάρισαν. Να διαγραφούν οριστικά και όλες οι φωτογραφίες που ανέβηκαν για το παραπάνω τμήμα και την ημερομηνία, μαζί με όσες είναι κρυφές;</p>
      <div id="photo-clear-day-warning" class="alert alert-warning">Η διαγραφή δεν αναιρείται. Τα αρχεία αφαιρούνται και τα links, ακόμη και σε email που έχουν ήδη σταλεί, παύουν να λειτουργούν.</div>
      <div class="clear-form-actions">
        <button class="btn btn-secondary" id="photo-clear-day-cancel" type="button" autofocus onclick="closePhotoClearDayModal(false)">Διατήρηση φωτογραφιών</button>
        <button class="btn btn-danger" type="button" onclick="closePhotoClearDayModal(true)"><span data-icon="trash" aria-hidden="true"></span>Οριστική διαγραφή</button>
      </div>
    </dialog>
  </div>
</div>

<!-- Native dialog supplies modal focus containment and Escape-to-cancel. -->
<dialog id="clear-form-dialog" class="clear-form-dialog" aria-labelledby="clear-form-title" aria-describedby="clear-form-description clear-form-note" oncancel="event.preventDefault(); closeClearFormConfirmation(false)">
  <div class="clear-form-heading">
    <span class="clear-form-symbol" data-icon="refresh" aria-hidden="true"></span>
    <button class="icon-button clear-form-close" type="button" aria-label="Ακύρωση καθαρισμού" onclick="closeClearFormConfirmation(false)"><span data-icon="close" aria-hidden="true"></span></button>
  </div>
  <h2 id="clear-form-title">Καθαρισμός φόρμας;</h2>
  <p id="clear-form-context" class="clear-form-context"></p>
  <p id="clear-form-description">Θα μηδενιστούν οι βαθμολογίες και θα καθαριστούν τα πεδία και οι επιλογές της φόρμας. Οι μη αποθηκευμένες αλλαγές θα χαθούν.</p>
  <div id="clear-form-note" class="clear-form-note"><span data-icon="lock" aria-hidden="true"></span><p>Τα αποθηκευμένα μηνύματα και οι απουσίες δεν διαγράφονται. Για αναίρεση απουσίας αποεπιλέξτε το «Απών». Θα ακολουθήσει ξεχωριστή επιβεβαίωση για οριστική διαγραφή των φωτογραφιών του τμήματος και της ημερομηνίας.</p></div>
  <div class="clear-form-actions">
    <button class="btn btn-secondary" id="clear-form-cancel" type="button" autofocus onclick="closeClearFormConfirmation(false)">Ακύρωση</button>
    <button class="btn btn-clear-confirm" type="button" onclick="closeClearFormConfirmation(true)"><span data-icon="refresh" aria-hidden="true"></span>Καθαρισμός φόρμας</button>
  </div>
</dialog>

<script>
var CSRF = '<?= $csrf ?>';
var IS_ADMIN = <?= !empty($isAdmin) ? 'true' : 'false' ?>;
var PHOTO_HIDDEN_GRACE_DAYS = <?= (int)($photoHiddenGraceDays ?? 15) ?>;
var childrenData = [];
var photoChildrenOptions = [];
var photoDupDecisionResolver = null;
var photoDupRememberedAction = '';
var photoClearDayResolver = null;

var ratingLabels = {0:'-',1:'Καθόλου',2:'Μέτρια',3:'Καλά',4:'Πολύ Καλά'};
var ratingColors  = {0:'',1:'#e74c3c',2:'#e8a020',3:'#3498db',4:'#27ae60'};

// Attendance comes only from the database, scoped to the loaded child/day.
var attendanceReady = false;
var attendancePending = false;
var messageBusy = false;
var loadedGroup = '';
var loadedDate = '';
var childrenLoadVersion = 0;

function composeScopeMatches() {
  return loadedGroup && loadedGroup === document.getElementById('sel-group').value &&
    loadedDate === document.getElementById('sel-date').value;
}

function updateComposeControls() {
  var busy = attendancePending || messageBusy;
  var loaded = composeScopeMatches() && childrenData.length > 0;
  ['sel-group', 'sel-date', 'btn-load-children'].forEach(function(id) { document.getElementById(id).disabled = busy; });
  ['btn-save-all', 'btn-clear-all', 'btn-preview-email'].forEach(function(id) { document.getElementById(id).disabled = busy || !loaded; });
  document.getElementById('btn-send-email').disabled = busy || !loaded || !attendanceReady;
  document.querySelectorAll('.absent-today-toggle').forEach(function(el) { el.disabled = busy || !loaded || !attendanceReady; });
}

function toggleAbsentToday(el, childId) {
  var child = childrenData.find(function(r) { return String(r.id) === String(childId); });
  if (!child) return;
  var previous = child.is_absent === true;
  var absent = el.checked;
  if (!attendanceReady || attendancePending || messageBusy || !composeScopeMatches()) {
    el.checked = previous;
    showToast('Φορτώστε το τμήμα και περιμένετε να ολοκληρωθεί η αποθήκευση.', 'warning');
    return;
  }
  var gid = loadedGroup, date = loadedDate;
  attendancePending = true;
  updateComposeControls();
  var notice = document.getElementById('attendance-notice');
  notice.textContent = 'Αποθήκευση απουσίας...';
  apiPost('/api/messages/attendance', {group_id: gid, child_id: childId, date: date, is_absent: absent ? '1' : '0', _token: CSRF}, function(err, resp) {
    attendancePending = false;
    if (err || !resp || resp.success !== true || resp.date !== date || String(resp.child_id) !== String(childId) || resp.is_absent !== absent) {
      el.checked = previous;
      // A lost response may hide a successful write: force a reload before send.
      attendanceReady = false;
      notice.textContent = (resp && resp.error) || 'Δεν επιβεβαιώθηκε η αποθήκευση. Φορτώστε ξανά το τμήμα πριν από αποστολή.';
      showToast('Η αποθήκευση απουσίας δεν επιβεβαιώθηκε. Απαιτείται νέα φόρτωση.', 'danger');
    } else {
      child.is_absent = absent;
      var row = el.closest('tr');
      row.style.opacity = absent ? '0.5' : '';
      row.style.background = absent ? '#fff7ed' : '';
      row.querySelector('.attendance-email-status').innerHTML = attendanceEmailStatus(child.email_status, absent);
      notice.textContent = 'Αποθηκεύτηκε για ' + date.split('-').reverse().join('/') + ': ' + (absent ? 'Απών.' : 'Αναίρεση απουσίας.');
      showToast('Η απουσία αποθηκεύτηκε.', 'success');
    }
    updateAbsentTodayBadge();
    updateComposeControls();
  });
}

function getAbsentTodayChildIds() {
  return childrenData.filter(function(r) { return r.is_absent === true; }).map(function(r) { return Number(r.id); });
}

function updateAbsentTodayBadge() {
  var badge = document.getElementById('absent-today-badge');
  if (!badge) return;
  var count = getAbsentTodayChildIds().length;
  if (count > 0) {
    var label = count === 1
      ? '1 απών στην επιλεγμένη ημερομηνία (παράλειψη email)'
      : count + ' απόντα στην επιλεγμένη ημερομηνία (παράλειψη email)';
    badge.textContent = label;
    badge.style.display = 'inline-flex';
  } else {
    badge.textContent = '';
    badge.style.display = 'none';
  }
}

function invalidateComposeScope() {
  childrenLoadVersion++;
  loadedGroup = loadedDate = '';
  childrenData = [];
  attendanceReady = false;
  document.getElementById('children-area').textContent = 'Πατήστε Φόρτωση για το επιλεγμένο τμήμα και την ημερομηνία.';
  document.getElementById('attendance-notice').textContent = '';
  updateComposeControls();
}

function loadChildren() {
  if (attendancePending || messageBusy) return;
  var gid  = document.getElementById('sel-group').value;
  var date = document.getElementById('sel-date').value;
  if (!gid) { showToast('Επιλέξτε τμήμα.','warning'); return; }
  invalidateComposeScope();
  var version = childrenLoadVersion;
  document.getElementById('children-area').innerHTML = '<div class="text-muted text-center" style="padding:20px;">Φόρτωση...</div>';
  document.getElementById('btn-save-all').disabled   = true;
  document.getElementById('btn-send-email').disabled = true;
  document.getElementById('btn-preview-email').disabled = true;

  apiPost('/api/messages/list-by-group', {group_id:gid, date:date, _token:CSRF}, function(err, resp) {
    if (version !== childrenLoadVersion || gid !== document.getElementById('sel-group').value || date !== document.getElementById('sel-date').value) return;
    if (err||!resp||!resp.rows) {
      document.getElementById('children-area').textContent = (resp && resp.error) || 'Σφάλμα φόρτωσης.';
      showToast((resp && resp.error) || 'Σφάλμα φόρτωσης.','danger');
      return;
    }
    loadedGroup = gid;
    loadedDate = date;
    attendanceReady = resp.attendance_ready === true;
    resp.rows.forEach(function(r) { r.is_absent = r.is_absent === true || r.is_absent === 1 || r.is_absent === '1'; });
    childrenData = resp.rows;
    renderChildrenTable(resp.rows);
    loadPhotoChildren(resp.rows);
    var notice = document.getElementById('attendance-notice');
    notice.textContent = attendanceReady
      ? 'Το «Απών» αποθηκεύεται αμέσως στη βάση για την επιλεγμένη ημερομηνία, ανεξάρτητα από την αποθήκευση μηνυμάτων.'
      : (resp.attendance_error || 'Η αποθήκευση απουσιών δεν είναι διαθέσιμη. Ζητήστε ενεργοποίηση στις Παραμέτρους.');
    // Fresh installations use DB attendance only; never read another app's local flags.
    updateComposeControls();
  });
}

function loadPhotoChildren(rows) {
  photoChildrenOptions = rows.map(function(r) {
    return {
      id: r.id,
      label: (r.last_name || '') + ' ' + (r.first_name || '')
    };
  });
  renderPhotoChildrenOptions('');
  renderGroupTargetChildrenOptions('');
  var searchInput = document.getElementById('photo-child-search');
  if (searchInput) searchInput.value = '';
  var groupSearchInput = document.getElementById('photo-group-child-search');
  if (groupSearchInput) groupSearchInput.value = '';
  hidePhotoStorageAlert();
  hidePhotoUploadFeedback();
  document.getElementById('photo-list').innerHTML = 'Επιλέξτε παιδί για να δείτε/ανεβάσετε φωτογραφίες.';
}

function renderPhotoChildrenOptions(filterText) {
  var sel = document.getElementById('photo-child');
  if (!sel) return;

  var selectedValue = sel.value || '';
  var filter = (filterText || '').toLowerCase();
  sel.innerHTML = '<option value="">-- Επιλέξτε παιδί --</option>';

  photoChildrenOptions.forEach(function(child) {
    var label = (child.label || '').trim();
    if (!filter || label.toLowerCase().indexOf(filter) !== -1) {
      var opt = document.createElement('option');
      opt.value = child.id;
      opt.textContent = label;
      sel.appendChild(opt);
    }
  });

  if (selectedValue) {
    sel.value = selectedValue;
  }
}

function filterPhotoChildren() {
  var input = document.getElementById('photo-child-search');
  renderPhotoChildrenOptions(input ? input.value : '');
}

function renderGroupTargetChildrenOptions(filterText) {
  var list = document.getElementById('photo-group-children-list');
  if (!list) return;

  window.groupTargetSelectedMap = window.groupTargetSelectedMap || {};

  var filter = (filterText || '').toLowerCase();
  list.innerHTML = '';

  var shown = 0;

  photoChildrenOptions.forEach(function(child) {
    var label = (child.label || '').trim();
    if (!filter || label.toLowerCase().indexOf(filter) !== -1) {
      shown++;
      var row = document.createElement('label');
      row.style.display = 'flex';
      row.style.alignItems = 'center';
      row.style.gap = '8px';
      row.style.fontSize = '12px';
      row.style.padding = '2px 0';

      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = String(child.id);
      cb.checked = !!window.groupTargetSelectedMap[String(child.id)];
      cb.onchange = function() {
        window.groupTargetSelectedMap[String(child.id)] = !!cb.checked;
        updateGroupTargetSelectedCount();
      };

      var text = document.createElement('span');
      text.textContent = label;

      row.appendChild(cb);
      row.appendChild(text);
      list.appendChild(row);
    }
  });

  if (shown === 0) {
    list.innerHTML = '<div class="text-muted" style="font-size:12px;">Δεν βρέθηκαν παιδιά.</div>';
  }

  updateGroupTargetSelectedCount();
}

function filterGroupTargetChildren() {
  var input = document.getElementById('photo-group-child-search');
  renderGroupTargetChildrenOptions(input ? input.value : '');
}

function getSelectedGroupChildIds() {
  window.groupTargetSelectedMap = window.groupTargetSelectedMap || {};
  var ids = [];
  Object.keys(window.groupTargetSelectedMap).forEach(function(key) {
    if (window.groupTargetSelectedMap[key]) {
      var id = parseInt(key, 10);
      if (id > 0) {
        ids.push(id);
      }
    }
  });
  return ids;
}

function updateGroupTargetSelectedCount() {
  var el = document.getElementById('photo-group-selected-count');
  if (!el) return;
  var count = getSelectedGroupChildIds().length;
  el.textContent = count + ' επιλεγμένα';
}

function selectAllGroupTargetChildren(checked) {
  window.groupTargetSelectedMap = window.groupTargetSelectedMap || {};
  photoChildrenOptions.forEach(function(child) {
    window.groupTargetSelectedMap[String(child.id)] = !!checked;
  });
  var input = document.getElementById('photo-group-child-search');
  renderGroupTargetChildrenOptions(input ? input.value : '');
}

document.getElementById('photo-child').addEventListener('change', function() {
  var childId = this.value;
  if (!childId) {
    document.getElementById('photo-list').innerHTML = 'Επιλέξτε παιδί για να δείτε/ανεβάσετε φωτογραφίες.';
    return;
  }
  loadPhotoList();
});

function loadPhotoList(preserveStatusMessages) {
  var gid = document.getElementById('sel-group').value;
  var date = document.getElementById('sel-date').value;
  var childId = document.getElementById('photo-child').value;
  var includeHidden = IS_ADMIN && document.getElementById('photo-show-hidden') && document.getElementById('photo-show-hidden').checked ? 1 : 0;
  var allDates = document.getElementById('photo-all-dates') && document.getElementById('photo-all-dates').checked ? 1 : 0;
  if (!gid || !childId) {
    return;
  }

  if (!preserveStatusMessages) {
    hidePhotoStorageAlert();
    hidePhotoUploadFeedback();
  }
  document.getElementById('photo-list').innerHTML = '<span class="text-muted">Φόρτωση φωτογραφιών...</span>';
  apiPost('/api/messages/photos/list', {group_id: gid, child_id: childId, date: date, include_hidden: includeHidden, all_dates: allDates, _token: CSRF}, function(err, resp) {
    if (err || !resp) {
      document.getElementById('photo-list').innerHTML = '<span class="alert alert-danger">Σφάλμα φόρτωσης φωτογραφιών.</span>';
      return;
    }
    if (resp.error) {
      document.getElementById('photo-list').innerHTML = '<span class="alert alert-warning">' + esc(resp.error) + '</span>';
      return;
    }
    renderPhotoList(resp.rows || []);
  });
}

function renderPhotoList(rows) {
  if (!rows.length) {
    document.getElementById('photo-list').innerHTML = '<span class="text-muted">Δεν υπάρχουν φωτογραφίες για το επιλεγμένο παιδί.</span>';
    return;
  }

  rows = sortPhotoRows(rows.slice());

  var html = '<div style="overflow-x:auto;"><table class="data-table" style="min-width:640px;">';
  html += '<thead><tr><th style="width:34px;text-align:center;"><input type="checkbox" id="photo-select-all" onclick="toggleAllPhotos(this.checked)"></th><th>Αρχείο</th><th>Ημερομηνία</th><th>Μέγεθος</th><th>Κατάσταση</th><th>Προβολή</th><th>Ενέργεια</th></tr></thead><tbody>';
  rows.forEach(function(r) {
    var hidden = !!r.hidden_at;
    html += '<tr' + (hidden ? ' style="opacity:0.62;background:#fff7ea;"' : '') + '>';
    html += '<td class="text-center"><input type="checkbox" class="photo-select" value="' + parseInt(r.id, 10) + '" onchange="updateSelectedPhotosUI()"></td>';
    html += '<td>' + esc(r.original_name || '') + '</td>';
    html += '<td>' + esc(r.message_date || '-') + '</td>';
    html += '<td>' + formatBytes(parseInt(r.size_bytes || 0, 10)) + '</td>';
    html += '<td>' + (hidden ? '<span style="color:#b05a00;font-weight:700;">Κρυφή από UI</span>' : '<span style="color:#2f7d32;font-weight:600;">Ορατή</span>') + '</td>';
    html += '<td><a href="' + esc(r.url || '#') + '" target="_blank" rel="noopener">Άνοιγμα</a></td>';
    html += '<td>';
    if (hidden) {
      html += '<button class="btn btn-sm" style="background:#2f7d32;color:#fff;" onclick="unhidePhoto(' + parseInt(r.id, 10) + ')">Επανεμφάνιση</button>';
    } else {
      html += '<button class="btn btn-sm btn-danger" onclick="deletePhoto(' + parseInt(r.id, 10) + ')">Απόκρυψη</button>';
    }
    if (IS_ADMIN) {
      html += ' <button class="btn btn-sm" style="background:#7a0f0f;color:#fff;" onclick="purgePhoto(' + parseInt(r.id, 10) + ')">Οριστική Διαγραφή</button>';
    }
    html += '</td>';
    html += '</tr>';
  });
  html += '</tbody></table></div>';
  html += '<div class="text-muted" id="photo-selected-count" style="margin-top:8px;font-size:12px;">0 επιλεγμένες φωτογραφίες</div>';
  document.getElementById('photo-list').innerHTML = html;
  updateSelectedPhotosUI();
}

function uploadPhotos() {
  sendPhotosUpload(false);
}

function uploadPhotosByMode() {
  var modeEl = document.getElementById('photo-upload-mode');
  var mode = modeEl ? modeEl.value : 'single';
  if (mode === 'selected') {
    uploadPhotosToSelectedChildren();
    return;
  }
  uploadPhotos();
}

function onPhotoUploadModeChange() {
  var modeEl = document.getElementById('photo-upload-mode');
  var btnEl = document.getElementById('btn-photo-upload-main');
  if (!modeEl || !btnEl) return;
  if (modeEl.value === 'selected') {
    btnEl.title = 'Για όλο το τμήμα: πατήστε «Επιλογή Όλων» και μετά «Μεταφόρτωση».';
  } else {
    btnEl.title = 'Μεταφόρτωση ανά παιδί';
  }
}

function uploadPhotosToSelectedChildren() {
  var ids = getSelectedGroupChildIds();
  if (!ids.length) {
    showToast('Επιλέξτε τουλάχιστον ένα παιδί από τη λίστα.','warning');
    return;
  }
  sendPhotosUploadGroup(false, true, ids);
}

function setUploadBtnLoading(loading) {
  var btn = document.getElementById('btn-photo-upload-main');
  if (!btn) return;
  if (loading) {
    btn.disabled = true;
    btn.setAttribute('data-original-text', btn.textContent);
    btn.textContent = '⏳ Μεταφόρτωση...';
  } else {
    btn.disabled = false;
    var orig = btn.getAttribute('data-original-text');
    if (orig) btn.textContent = orig;
  }
}

function sendPhotosUpload(forceOverwrite, skipDuplicates) {
  var gid = document.getElementById('sel-group').value;
  var date = document.getElementById('sel-date').value;
  var childId = document.getElementById('photo-child').value;
  var input = document.getElementById('photo-files');

  if (!gid) { showToast('Επιλέξτε τμήμα.','warning'); return; }
  if (!childId) { showToast('Επιλέξτε παιδί.','warning'); return; }
  if (!input.files || !input.files.length) { showToast('Επιλέξτε τουλάχιστον μία εικόνα.','warning'); return; }

  hidePhotoUploadFeedback();
  setUploadBtnLoading(true);

  var formData = new FormData();
  formData.append('group_id', gid);
  formData.append('child_id', childId);
  formData.append('date', date);
  formData.append('_token', CSRF);
  if (forceOverwrite) {
    formData.append('force_overwrite', '1');
  }
  if (skipDuplicates) {
    formData.append('skip_duplicates', '1');
  }
  for (var i = 0; i < input.files.length; i++) {
    formData.append('photos[]', input.files[i]);
  }

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + '/api/messages/photos/upload', true);
  xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
  xhr.onload = function() {
    var resp = null;
    try { resp = JSON.parse(xhr.responseText); } catch (e) {}

    setUploadBtnLoading(false);
    if (resp && resp.error_code === 'PHOTO_NAME_EXISTS') {
      var dupEntries = (resp.duplicates_details || resp.duplicates || []).slice(0, 6);
      openPhotoDupDecisionModal({
        summary: 'Εντοπίστηκαν διπλότυπα: ' + parseInt((resp.duplicates || []).length || dupEntries.length || 0, 10),
        details: dupEntries
      }, function(action) {
        if (action === 'replace') {
          sendPhotosUpload(true, false);
          return;
        }
        if (action === 'skip') {
          sendPhotosUpload(false, true);
          return;
        }
        showToast('Η μεταφόρτωση ακυρώθηκε.', 'info');
      });
      return;
    }

    if (resp && resp.error_code === 'PHOTO_STORAGE_FAILED') {
      showPhotoStorageAlert(resp.error + ' Επιλέξτε υπάρχουσες φωτογραφίες και κάντε οριστική διαγραφή για να ελευθερωθεί χώρος.');
      showToast(resp.error, 'danger');
      loadPhotoList(true);
      return;
    }

    if (!resp || resp.error) {
      showToast(resp && resp.error ? resp.error : 'Αποτυχία μεταφόρτωσης.','danger');
      return;
    }

    var skippedCount = parseInt(resp.skipped_duplicates_count || 0, 10) || 0;
    var skippedList = Array.isArray(resp.skipped_duplicates) ? resp.skipped_duplicates : [];

    var msg = 'Ανέβηκαν ' + (resp.saved || 0) + ' εικόνες.';
    if (skippedCount > 0) {
      msg += ' Παραλείφθηκαν διπλότυπα: ' + skippedCount + '.';
    }
    if (resp.errors && resp.errors.length) {
      msg += ' Απορρίφθηκαν: ' + resp.errors.length;
      var combined = resp.errors.slice();
      if (skippedList.length) {
        combined = combined.concat(skippedList.map(function(name) {
          return name + ': παραλείφθηκε ως διπλότυπο.';
        }));
      }
      showPhotoUploadFeedback(combined, (resp.saved || 0) > 0 || skippedCount > 0 ? 'warning' : 'danger');
    } else if (skippedList.length) {
      showPhotoUploadFeedback(skippedList.map(function(name) {
        return name + ': παραλείφθηκε ως διπλότυπο.';
      }), 'warning');
    } else {
      hidePhotoUploadFeedback();
    }
    showToast(msg, (resp.errors && resp.errors.length) || skippedCount > 0 ? 'warning' : 'success');
    input.value = '';
    loadPhotoList(!!(resp.errors && resp.errors.length));
  };
  xhr.onerror = function() { showToast('Σφάλμα δικτύου στη μεταφόρτωση.','danger'); };
  xhr.send(formData);
}

async function sendPhotosUploadGroup(forceOverwrite, selectedOnly, selectedIds, skipDuplicates, suppressInitialConfirm) {
  var gid = document.getElementById('sel-group').value;
  var date = document.getElementById('sel-date').value;
  var input = document.getElementById('photo-files');

  if (!gid) { showToast('Επιλέξτε τμήμα.','warning'); return; }
  if (!input.files || !input.files.length) { showToast('Επιλέξτε τουλάχιστον μία εικόνα.','warning'); return; }

  if (!forceOverwrite && !suppressInitialConfirm) {
    var filesCount = input.files.length;
    var childrenCount = selectedOnly ? (selectedIds || []).length : (Array.isArray(childrenData) ? childrenData.length : 0);
    if (!childrenCount) {
      showToast('Δεν υπάρχουν φορτωμένα παιδιά για το επιλεγμένο τμήμα. Πατήστε πρώτα Φόρτωση.','warning');
      return;
    }
    var scopeText = selectedOnly ? 'στα επιλεγμένα παιδιά' : 'σε όλο το τμήμα';
    if (!await appConfirm('Η μεταφόρτωση θα αντιγράψει ' + filesCount + ' φωτογραφία/ες σε ' + childrenCount + ' παιδιά (' + scopeText + '). Συνέχεια;', {title: 'Μαζική μεταφόρτωση φωτογραφιών', confirmLabel: 'Μεταφόρτωση'})) {
      return;
    }
  }

  hidePhotoUploadFeedback();
  setUploadBtnLoading(true);

  var formData = new FormData();
  formData.append('group_id', gid);
  formData.append('date', date);
  formData.append('_token', CSRF);
  if (forceOverwrite) {
    formData.append('force_overwrite', '1');
  }
  if (skipDuplicates) {
    formData.append('skip_duplicates', '1');
  }
  if (selectedOnly && Array.isArray(selectedIds)) {
    selectedIds.forEach(function(id) {
      formData.append('child_ids[]', String(id));
    });
  }
  for (var i = 0; i < input.files.length; i++) {
    formData.append('photos[]', input.files[i]);
  }

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + '/api/messages/photos/upload-group', true);
  xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
  xhr.onload = function() {
    var resp = null;
    try { resp = JSON.parse(xhr.responseText); } catch (e) {}

    setUploadBtnLoading(false);
    if (resp && resp.error_code === 'PHOTO_NAME_EXISTS_GROUP') {
      var dupList = (resp.duplicates_details || []).slice(0, 12);
      var total = parseInt(resp.duplicates_count || 0, 10);
      openPhotoDupDecisionModal({
        summary: total > 0 ? 'Εντοπίστηκαν διπλότυπα στο τμήμα: ' + total : 'Εντοπίστηκαν διπλότυπα στο τμήμα.',
        details: dupList
      }, function(action) {
        if (action === 'replace') {
          sendPhotosUploadGroup(true, selectedOnly, selectedIds || [], false, true);
          return;
        }
        if (action === 'skip') {
          sendPhotosUploadGroup(false, selectedOnly, selectedIds || [], true, true);
          return;
        }
        showToast('Η μεταφόρτωση στο τμήμα ακυρώθηκε.', 'info');
      });
      return;
    }

    if (resp && resp.error_code === 'PHOTO_STORAGE_FAILED') {
      setUploadBtnLoading(false);
      showPhotoStorageAlert(resp.error + ' Επιλέξτε υπάρχουσες φωτογραφίες και κάντε οριστική διαγραφή για να ελευθερωθεί χώρος.');
      showToast(resp.error, 'danger');
      loadPhotoList(true);
      return;
    }

    if (!resp || resp.error) {
      setUploadBtnLoading(false);
      showToast(resp && resp.error ? resp.error : 'Αποτυχία μεταφόρτωσης στο τμήμα.','danger');
      if (resp && resp.errors && resp.errors.length) {
        showPhotoUploadFeedback(resp.errors, 'danger');
      }
      return;
    }

    var modeText = selectedOnly ? 'επιλεγμένα παιδιά' : 'όλο το τμήμα';
    var skippedCount = parseInt(resp.skipped_duplicates_count || 0, 10) || 0;
    var skippedList = Array.isArray(resp.skipped_duplicates) ? resp.skipped_duplicates : [];
    var msg = 'Ανέβηκαν ' + (resp.saved || 0) + ' φωτογραφίες συνολικά σε ' + (resp.children_count || 0) + ' παιδιά (' + modeText + ').';
    if (skippedCount > 0) {
      msg += ' Παραλείφθηκαν διπλότυπα: ' + skippedCount + '.';
    }
    if (resp.errors && resp.errors.length) {
      msg += ' Απορρίφθηκαν: ' + resp.errors.length;
      var combinedErrors = resp.errors.slice();
      if (skippedList.length) {
        combinedErrors = combinedErrors.concat(skippedList.map(function(name) {
          return name + ': παραλείφθηκε ως διπλότυπο.';
        }));
      }
      showPhotoUploadFeedback(combinedErrors, (resp.saved || 0) > 0 || skippedCount > 0 ? 'warning' : 'danger');
    } else if (skippedList.length) {
      showPhotoUploadFeedback(skippedList.map(function(name) {
        return name + ': παραλείφθηκε ως διπλότυπο.';
      }), 'warning');
    } else {
      hidePhotoUploadFeedback();
    }

    showToast(msg, (resp.errors && resp.errors.length) || skippedCount > 0 ? 'warning' : 'success');
    setUploadBtnLoading(false);
    input.value = '';
    if (document.getElementById('photo-child').value) {
      loadPhotoList(!!(resp.errors && resp.errors.length));
    }
  };
  xhr.onerror = function() { setUploadBtnLoading(false); showToast('Σφάλμα δικτύου στη μεταφόρτωση.','danger'); };
  xhr.send(formData);
}

function openPhotoDupDecisionModal(data, onDecision) {
  var modal = document.getElementById('photo-dup-modal');
  var summaryEl = document.getElementById('photo-dup-modal-summary');
  var listEl = document.getElementById('photo-dup-modal-list');
  var rememberEl = document.getElementById('photo-dup-modal-remember');

  if (photoDupRememberedAction === 'replace' || photoDupRememberedAction === 'skip') {
    if (typeof onDecision === 'function') {
      onDecision(photoDupRememberedAction);
    }
    return;
  }

  if (!modal || !summaryEl || !listEl) {
    if (typeof onDecision === 'function') onDecision('cancel');
    return;
  }

  var summary = data && data.summary ? String(data.summary) : '';
  var details = data && Array.isArray(data.details) ? data.details : [];

  summaryEl.textContent = summary;
  if (details.length) {
    var html = '<ul style="margin:0;padding-left:18px;">';
    details.forEach(function(item) {
      html += '<li>' + esc(String(item || '')) + '</li>';
    });
    html += '</ul>';
    listEl.innerHTML = html;
  } else {
    listEl.innerHTML = '<span class="text-muted">Δεν υπάρχουν επιπλέον λεπτομέρειες.</span>';
  }

  photoDupDecisionResolver = typeof onDecision === 'function' ? onDecision : null;
  if (rememberEl) {
    rememberEl.checked = false;
  }
  modal.style.display = 'flex';
}

function closePhotoDupDecisionModal(action) {
  var modal = document.getElementById('photo-dup-modal');
  var rememberEl = document.getElementById('photo-dup-modal-remember');
  if (modal) {
    modal.style.display = 'none';
  }

  if ((action === 'replace' || action === 'skip') && rememberEl && rememberEl.checked) {
    photoDupRememberedAction = action;
    updatePhotoDupRememberBanner();
  }

  var resolver = photoDupDecisionResolver;
  photoDupDecisionResolver = null;
  if (typeof resolver === 'function') {
    resolver(action || 'cancel');
  }
}

function clearPhotoDupRememberedAction() {
  photoDupRememberedAction = '';
  updatePhotoDupRememberBanner();
  showToast('Η απομνημονευμένη επιλογή διπλότυπων καθαρίστηκε.', 'info');
}

function updatePhotoDupRememberBanner() {
  var el = document.getElementById('photo-dup-remember-banner');
  if (!el) return;

  if (photoDupRememberedAction !== 'replace' && photoDupRememberedAction !== 'skip') {
    el.style.display = 'none';
    el.innerHTML = '';
    return;
  }

  var text = photoDupRememberedAction === 'replace'
    ? 'Αντικατάσταση διπλότυπων'
    : 'Παράλειψη διπλότυπων';

  el.innerHTML = 'Ενεργή απομνημονευμένη επιλογή διπλότυπων: <strong>' + esc(text) + '</strong>. '
    + '<a href="#" onclick="clearPhotoDupRememberedAction();return false;">Αλλαγή</a>';
  el.style.display = 'block';
}

async function deletePhoto(id) {
  if (!await appConfirm('Να κρυφτεί η φωτογραφία από το UI; Το link θα μείνει ενεργό για ' + PHOTO_HIDDEN_GRACE_DAYS + ' ημέρες.', {title: 'Απόκρυψη φωτογραφίας', confirmLabel: 'Απόκρυψη'})) return;
  apiPost('/api/messages/photos/delete', {id: id, _token: CSRF}, function(err, resp) {
    if (err || !resp || resp.error) {
      showToast(resp && resp.error ? resp.error : 'Αποτυχία απόκρυψης φωτογραφίας.','danger');
      return;
    }
    showToast('Η φωτογραφία κρύφτηκε. Το link παραμένει ενεργό για ' + PHOTO_HIDDEN_GRACE_DAYS + ' ημέρες.','success');
    refreshPhotoListAfterVisibilityChange('hide');
  });
}

async function unhidePhoto(id) {
  if (!await appConfirm('Να επανεμφανιστεί η φωτογραφία στο UI;', {title: 'Επανεμφάνιση φωτογραφίας', confirmLabel: 'Επανεμφάνιση'})) return;
  apiPost('/api/messages/photos/unhide', {id: id, _token: CSRF}, function(err, resp) {
    if (err || !resp || resp.error) {
      showToast(resp && resp.error ? resp.error : 'Αποτυχία επανεμφάνισης φωτογραφίας.','danger');
      return;
    }
    showToast('Η φωτογραφία επανεμφανίστηκε.','success');
    refreshPhotoListAfterVisibilityChange('unhide');
  });
}

function refreshPhotoListAfterVisibilityChange(mode) {
  var showHidden = document.getElementById('photo-show-hidden');
  if (mode === 'hide' && showHidden && showHidden.checked) {
    showHidden.checked = false;
  }
  loadPhotoList();
}

async function purgePhoto(id) {
  if (!IS_ADMIN) {
    showToast('Μόνο διαχειριστής μπορεί να κάνει οριστική διαγραφή.','warning');
    return;
  }
  if (!await appConfirm('Το αρχείο θα διαγραφεί οριστικά και το link θα σταματήσει να λειτουργεί, ακόμη και σε email που έχουν ήδη σταλεί.', {title: 'Οριστική διαγραφή φωτογραφίας;', confirmLabel: 'Οριστική διαγραφή', danger: true})) return;

  apiPost('/api/messages/photos/purge', {id: id, _token: CSRF}, function(err, resp) {
    if (err || !resp || resp.error) {
      showToast(resp && resp.error ? resp.error : 'Αποτυχία οριστικής διαγραφής φωτογραφίας.','danger');
      return;
    }
    showToast('Η φωτογραφία διαγράφηκε οριστικά.','success');
    loadPhotoList();
  });
}

function getSelectedPhotoIds() {
  var ids = [];
  document.querySelectorAll('.photo-select:checked').forEach(function(el) {
    ids.push(parseInt(el.value, 10));
  });
  return ids.filter(function(id) { return id > 0; });
}

function updateSelectedPhotosUI() {
  var ids = getSelectedPhotoIds();
  var countEl = document.getElementById('photo-selected-count');
  if (countEl) {
    countEl.textContent = ids.length + ' επιλεγμένες φωτογραφίες';
  }
  var all = document.querySelectorAll('.photo-select');
  var checked = document.querySelectorAll('.photo-select:checked');
  var selectAll = document.getElementById('photo-select-all');
  if (selectAll) {
    selectAll.checked = all.length > 0 && all.length === checked.length;
  }
}

function toggleAllPhotos(checked) {
  document.querySelectorAll('.photo-select').forEach(function(el) {
    el.checked = checked;
  });
  updateSelectedPhotosUI();
}

async function bulkHideSelectedPhotos() {
  var ids = getSelectedPhotoIds();
  if (!ids.length) {
    showToast('Επιλέξτε πρώτα φωτογραφίες.','warning');
    return;
  }
  if (!await appConfirm('Να κρυφτούν οι ' + ids.length + ' επιλεγμένες φωτογραφίες από το UI; Τα links θα μείνουν ενεργά για ' + PHOTO_HIDDEN_GRACE_DAYS + ' ημέρες.', {title: 'Απόκρυψη επιλεγμένων φωτογραφιών', confirmLabel: 'Απόκρυψη'})) return;
  apiPost('/api/messages/photos/delete-selected', {ids: ids, _token: CSRF}, function(err, resp) {
    if (err || !resp || resp.error) {
      showToast(resp && resp.error ? resp.error : 'Αποτυχία απόκρυψης επιλεγμένων φωτογραφιών.','danger');
      return;
    }
    var hiddenNow = parseInt(resp.hidden_now != null ? resp.hidden_now : (resp.hidden || 0), 10) || 0;
    var alreadyHidden = parseInt(resp.already_hidden_count || 0, 10) || 0;
    var msg = '';
    var level = 'success';

    if (hiddenNow > 0 && alreadyHidden > 0) {
      msg = 'Κρύφτηκαν ' + hiddenNow + ' φωτογραφίες. ' + alreadyHidden + ' ήταν ήδη κρυμμένες.';
      level = 'info';
    } else if (hiddenNow > 0) {
      msg = 'Κρύφτηκαν ' + hiddenNow + ' φωτογραφίες.';
      level = 'success';
    } else if (alreadyHidden > 0) {
      msg = 'Οι επιλεγμένες φωτογραφίες είναι ήδη κρυμμένες.';
      level = 'info';
    } else {
      msg = 'Δεν βρέθηκαν φωτογραφίες για απόκρυψη.';
      level = 'warning';
    }

    showToast(msg, level);
    loadPhotoList();
  });
}

async function bulkPurgeSelectedPhotos() {
  var ids = getSelectedPhotoIds();
  if (!ids.length) {
    showToast('Επιλέξτε πρώτα φωτογραφίες.','warning');
    return;
  }
  if (!await appConfirm('Να γίνει οριστική διαγραφή των ' + ids.length + ' επιλεγμένων φωτογραφιών;', {title: 'Διαγραφή επιλεγμένων φωτογραφιών', confirmLabel: 'Συνέχεια', danger: true})) return;
  if (!await appConfirm('Τα links αυτών των φωτογραφιών θα σταματήσουν να λειτουργούν άμεσα, ακόμη και σε email που έχουν ήδη σταλεί.', {title: 'Τελική επιβεβαίωση διαγραφής', confirmLabel: 'Οριστική διαγραφή', danger: true})) return;
  apiPost('/api/messages/photos/purge-selected', {ids: ids, _token: CSRF}, function(err, resp) {
    if (err || !resp || resp.error) {
      showToast(resp && resp.error ? resp.error : 'Αποτυχία οριστικής διαγραφής επιλεγμένων φωτογραφιών.','danger');
      return;
    }
    hidePhotoStorageAlert();
    showToast('Διαγράφηκαν οριστικά ' + parseInt(resp.purged || 0, 10) + ' φωτογραφίες.','success');
    loadPhotoList();
  });
}

function showPhotoStorageAlert(message) {
  var el = document.getElementById('photo-storage-alert');
  if (!el) return;
  el.textContent = message;
  el.style.display = 'block';
}

function hidePhotoStorageAlert() {
  var el = document.getElementById('photo-storage-alert');
  if (!el) return;
  el.textContent = '';
  el.style.display = 'none';
}

function showPhotoUploadFeedback(errors, level) {
  var el = document.getElementById('photo-upload-feedback');
  if (!el) return;
  var items = Array.isArray(errors) ? errors : [];
  if (!items.length) {
    hidePhotoUploadFeedback();
    return;
  }

  var html = '<strong>Προβλήματα μεταφόρτωσης:</strong><ul style="margin:6px 0 0 18px;">';
  items.forEach(function(item) {
    html += '<li>' + esc(String(item || '')) + '</li>';
  });
  html += '</ul>';

  el.className = 'alert alert-' + (level || 'warning');
  el.innerHTML = html;
  el.style.display = 'block';
}

function hidePhotoUploadFeedback() {
  var el = document.getElementById('photo-upload-feedback');
  if (!el) return;
  el.innerHTML = '';
  el.style.display = 'none';
}

async function purgePhotosDay() {
  if (!IS_ADMIN) {
    showToast('Μόνο διαχειριστής μπορεί να κάνει οριστική διαγραφή.','warning');
    return;
  }

  var gid = document.getElementById('sel-group').value;
  var date = document.getElementById('sel-date').value;
  var childId = document.getElementById('photo-child').value || '';

  if (!gid) {
    showToast('Επιλέξτε τμήμα.','warning');
    return;
  }

  var scopeText = childId ? 'μόνο για το επιλεγμένο παιδί' : 'για ΟΛΟ το τμήμα';
  var groupName = document.getElementById('sel-group').selectedOptions[0].text;
  if (!await appConfirm('Οριστική διαγραφή φωτογραφιών ' + scopeText + '.\n' + groupName + ' · ' + date.split('-').reverse().join('/'), {title: 'Διαγραφή φωτογραφιών ημέρας', confirmLabel: 'Συνέχεια', danger: true})) return;
  if (!await appConfirm('Τα links θα σταματήσουν να λειτουργούν άμεσα, ακόμη και σε email που έχουν ήδη σταλεί.', {title: 'Τελική επιβεβαίωση διαγραφής', confirmLabel: 'Οριστική διαγραφή', danger: true})) return;

  apiPost('/api/messages/photos/purge-day', {group_id: gid, child_id: childId, date: date, _token: CSRF}, function(err, resp) {
    if (err || !resp || resp.error) {
      showToast(resp && resp.error ? resp.error : 'Αποτυχία οριστικής διαγραφής ημέρας.','danger');
      return;
    }
    showToast('Οριστική διαγραφή: ' + parseInt(resp.purged || 0, 10) + ' φωτογραφίες.','success');
    loadPhotoList();
  });
}

function formatBytes(bytes) {
  if (!bytes || bytes < 1024) return (bytes || 0) + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

function sortPhotoRows(rows) {
  var sortEl = document.getElementById('photo-sort');
  var mode = sortEl ? sortEl.value : 'size_desc';

  rows.sort(function(a, b) {
    var sizeA = parseInt(a.size_bytes || 0, 10);
    var sizeB = parseInt(b.size_bytes || 0, 10);
    var nameA = String(a.original_name || '').toLowerCase();
    var nameB = String(b.original_name || '').toLowerCase();
    var timeA = Date.parse(a.created_at || '') || 0;
    var timeB = Date.parse(b.created_at || '') || 0;

    if (mode === 'size_asc') return sizeA - sizeB;
    if (mode === 'name_asc') return nameA.localeCompare(nameB, 'el');
    if (mode === 'name_desc') return nameB.localeCompare(nameA, 'el');
    if (mode === 'oldest') return timeA - timeB;
    if (mode === 'newest') return timeB - timeA;
    return sizeB - sizeA;
  });

  return rows;
}

function renderChildrenTable(rows) {
  if (!rows.length) {
    document.getElementById('children-area').innerHTML = '<div class="text-muted text-center" style="padding:20px;">Δεν υπάρχουν παιδιά σε αυτό το τμήμα.</div>';
    return;
  }

  var absentCount = rows.filter(function(r) { return r.is_absent === true; }).length;

  var absentLabel = absentCount === 1
    ? '1 απών στην επιλεγμένη ημερομηνία (παράλειψη email)'
    : absentCount + ' απόντα στην επιλεγμένη ημερομηνία (παράλειψη email)';
  var badgeHtml = '<div id="absent-today-badge" style="display:' + (absentCount > 0 ? 'inline-flex' : 'none') + ';align-items:center;justify-content:center;background:#fff7ed;border:1px solid #fed7aa;color:#9a5b00;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:700;line-height:1.2;letter-spacing:0.2px;margin-bottom:10px;">' + (absentCount > 0 ? absentLabel : '') + '</div>';

  var html = badgeHtml + '<table class="data-table msg-table" style="min-width:980px;">';
  html += '<thead><tr>' +
    '<th>Παιδί</th>' +
    '<th style="width:110px;">Πρωινό</th>' +
    '<th style="width:110px;">Μεσημ/νό</th>' +
    '<th style="width:110px;">Διάθεση</th>' +
    '<th style="width:80px;">Ύπνος (λεπτ)</th>' +
    '<th style="width:50px;">WC</th>' +
    '<th style="width:100px;">Απών</th>' +
    '<th>Δραστηριότητες</th>' +
    '<th>Σχόλια</th>' +
    '<th style="width:90px;">Email</th>' +
  '</tr></thead><tbody>';

  rows.forEach(function(r, idx) {
    var status = r.email_status || '';
    var absent = r.is_absent === true;
    var statusLabel = attendanceEmailStatus(status, absent);

    html += '<tr data-idx="'+idx+'"' + (absent ? ' style="opacity:0.5;background:#fff7ed;"' : '') + '>' +
      '<td style="white-space:nowrap;font-weight:bold;">' + esc(r.last_name + ' ' + r.first_name) + '</td>' +
      ratingCell(idx, 'breakfast', r.breakfast) +
      ratingCell(idx, 'lunch',     r.lunch) +
      ratingCell(idx, 'mood',      r.mood) +
      '<td><input type="number" min="0" max="480" style="width:60px;" class="msg-input" data-idx="'+idx+'" data-field="sleep_minutes" value="'+(r.sleep_minutes||0)+'"></td>' +
      '<td style="text-align:center;"><input type="checkbox" class="msg-cb" data-idx="'+idx+'" data-field="wc"'+(r.wc?'checked':'')+'/></td>' +
      '<td style="text-align:center;"><label style="display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:11px;"><input type="checkbox" class="absent-today-toggle" data-child-id="'+r.id+'" '+(absent ? 'checked' : '')+' onchange="toggleAbsentToday(this, '+r.id+')"> Απών</label></td>' +
      '<td><textarea rows="2" style="width:100%;min-width:160px;font-size:11px;" class="msg-input" data-idx="'+idx+'" data-field="activities_txt">'+esc(r.activities_txt||'')+'</textarea></td>' +
      '<td><textarea rows="2" style="width:100%;min-width:120px;font-size:11px;" class="msg-input" data-idx="'+idx+'" data-field="comments">'+esc(r.comments||'')+'</textarea></td>' +
      '<td class="attendance-email-status" style="text-align:center;">'+statusLabel+'</td>' +
    '</tr>';
  });
  html += '</tbody></table>';
  document.getElementById('children-area').innerHTML = html;
}

function attendanceEmailStatus(status, absent) {
  var labels = {pending: 'Αναμονή', sent: 'Εστάλη', failed: 'Αποτυχία', virtual: 'Εικονικό'};
  var result = labels[status] ? '<span class="status-badge status-' + status + '">' + labels[status] + '</span>' : '';
  // Never relabel an already sent email as "not sent" when marking absence later.
  if (absent) result += '<span style="display:block;color:#9a5b00;font-size:11px;">Απών — παράλειψη νέας αποστολής</span>';
  return result;
}

function ratingCell(idx, field, current) {
  current = current || 0;
  var html = '<td class="rating-cell" data-idx="'+idx+'" data-field="'+field+'">';
  for (var v=1; v<=4; v++) {
    var active = current == v ? 'active' : '';
    html += '<button type="button" class="rating-btn rating-'+v+' '+active+'" onclick="setRating(this,'+idx+',\''+field+'\','+v+')">'+v+'</button>';
  }
  html += '<input type="hidden" class="rating-val" data-idx="'+idx+'" data-field="'+field+'" value="'+current+'">';
  html += '</td>';
  return html;
}

function setRating(btn, idx, field, val) {
  var cell = btn.closest('.rating-cell');
  cell.querySelectorAll('.rating-btn').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  cell.querySelector('.rating-val').value = val;
}

function collectRows() {
  var rows = [];
  childrenData.forEach(function(c, idx) {
    var row = {child_id: c.id};
    document.querySelectorAll('.rating-val[data-idx="'+idx+'"]').forEach(function(el) {
      row[el.dataset.field] = el.value;
    });
    document.querySelectorAll('.msg-input[data-idx="'+idx+'"]').forEach(function(el) {
      row[el.dataset.field] = el.value;
    });
    document.querySelectorAll('.msg-cb[data-idx="'+idx+'"]').forEach(function(el) {
      row[el.dataset.field] = el.checked ? '1' : '0';
    });
    rows.push(row);
  });
  return rows;
}

function saveAll() {
  if (!composeScopeMatches() || attendancePending || messageBusy) return;
  var gid  = document.getElementById('sel-group').value;
  var date = document.getElementById('sel-date').value;
  var rows = collectRows();
  messageBusy = true;
  updateComposeControls();
  apiPost('/api/messages/save', {group_id:gid, date:date, rows:rows, _token:CSRF}, function(err,resp) {
    messageBusy = false;
    updateComposeControls();
    if (err||!resp||resp.error) { showToast('Σφάλμα αποθήκευσης.','danger'); return; }
    showToast('Αποθηκεύτηκαν '+resp.saved+' εγγραφές!','success');
  });
}

async function sendEmails() {
  if (!composeScopeMatches() || !attendanceReady || attendancePending || messageBusy) return;
  var gid = loadedGroup, date = loadedDate;
  if (!await appConfirm('Να αποσταλούν email για ' + date.split('-').reverse().join('/') + '; Τα παιδιά που έχουν αποθηκευμένη απουσία στη βάση θα παραλειφθούν.', {title: 'Αποστολή ενημερώσεων', confirmLabel: 'Αποστολή Email'})) return;
  if (!composeScopeMatches() || loadedGroup !== gid || loadedDate !== date || !attendanceReady || attendancePending || messageBusy) return;
  messageBusy = true;
  updateComposeControls();

  // Auto-save first (absent children's ratings are still saved normally)
  var rows = collectRows();
  apiPost('/api/messages/save', {group_id:gid, date:date, rows:rows, _token:CSRF}, function(err, saved) {
    if (err || !saved || saved.success !== true) {
      messageBusy = false;
      updateComposeControls();
      showToast('Σφάλμα αποθήκευσης. Δοκιμάστε ξανά.','danger');
      return;
    }
    apiPost('/api/messages/send-emails', {group_id:gid, date:date, _token:CSRF}, function(err2, resp) {
      messageBusy = false;
      updateComposeControls();
      if (err2||!resp||resp.error) {
        showToast((resp && resp.error) ? resp.error : 'Σφάλμα αποστολής.','danger');
        return;
      }
      if ((resp.processed || 0) === 0) {
        showToast('Δεν υπάρχουν διαθέσιμα μηνύματα προς αποστολή στη συγκεκριμένη ημερομηνία/τμήμα.','warning');
        loadChildren();
        return;
      }
      if ((resp.sent || 0) === 0 && (resp.failed || 0) === 0 && (resp.skipped || 0) > 0) {
        var absentMsg = resp.skipped === 1
          ? 'Το παιδί έχει αποθηκευμένη απουσία για την επιλεγμένη ημερομηνία, οπότε δεν στάλθηκε email.'
          : 'Όλα τα παιδιά έχουν αποθηκευμένη απουσία για την επιλεγμένη ημερομηνία, οπότε δεν στάλθηκε κανένα email ('+resp.skipped+').';
        showToast(absentMsg,'info');
        loadChildren();
        return;
      }
      var msg = 'Email: Απεστάλησαν '+resp.sent+
        (resp.failed > 0 ? ', Αποτυχία: '+resp.failed : '') +
        (resp.skipped > 0 ? ', Δεν στάλθηκαν: '+resp.skipped+' (απουσία)' : '') +
        (resp.mode === 'virtual' ? ' (εικονική αποστολή)' : '');
      if (resp.error_detail) msg += ' | Λόγος: ' + resp.error_detail;
      showToast(msg, resp.failed > 0 ? 'warning' : 'success');
      loadChildren();
    });
  });
}

function bulkApply(panel, mode) {
  var cbClass = panel === 'both' ? 'act-both-cb' : 'act-email-cb';
  var selected = [];
  document.querySelectorAll('.'+cbClass+':checked').forEach(function(cb) {
    selected.push(cb.value);
  });
  if (!selected.length) { showToast('Επιλέξτε δραστηριότητες πρώτα.','warning'); return; }

  document.querySelectorAll('.msg-input[data-field="activities_txt"]').forEach(function(ta) {
    if (mode === 'replace') {
      ta.value = selected.join(', ');
    } else {
      var existing = ta.value ? ta.value.split(',').map(function(s){ return s.trim().toLowerCase(); }) : [];
      var toAdd = selected.filter(function(s){ return existing.indexOf(s.toLowerCase()) === -1; });
      if (toAdd.length) {
        ta.value = ta.value ? ta.value + ', ' + toAdd.join(', ') : toAdd.join(', ');
      }
    }
  });
}

function clearAll() {
  if (!composeScopeMatches() || attendancePending || messageBusy) return;
  var dialog = document.getElementById('clear-form-dialog');
  if (dialog.open) return;
  var group = document.getElementById('sel-group');
  var date = document.getElementById('sel-date').value;
  document.getElementById('clear-form-context').textContent =
    (group.value ? group.options[group.selectedIndex].text : 'Τρέχουσα φόρμα') +
    (date ? ' · ' + date.split('-').reverse().join('/') : '');
  dialog.showModal();
  document.getElementById('clear-form-cancel').focus();
}

function closeClearFormConfirmation(confirmed) {
  var dialog = document.getElementById('clear-form-dialog');
  if (!dialog.open) return;
  dialog.close();
  if (confirmed) resetMessageFormFields();
}

function resetMessageFormFields() {

  var gid = document.getElementById('sel-group').value;
  var date = document.getElementById('sel-date').value;

  function resetPhotoUI() {
    var photoChild = document.getElementById('photo-child');
    var photoFiles = document.getElementById('photo-files');
    if (photoChild) photoChild.value = '';
    if (photoFiles) photoFiles.value = '';
    document.getElementById('photo-list').innerHTML = 'Επιλέξτε παιδί για να δείτε/ανεβάσετε φωτογραφίες.';
  }

  document.querySelectorAll('.rating-btn').forEach(function(btn) {
    btn.classList.remove('active');
  });
  document.querySelectorAll('.rating-val').forEach(function(el) {
    el.value = 0;
  });
  document.querySelectorAll('.msg-input[data-field="sleep_minutes"]').forEach(function(el) {
    el.value = 0;
  });
  document.querySelectorAll('.msg-cb[data-field="wc"]').forEach(function(el) {
    el.checked = false;
  });
  document.querySelectorAll('.msg-input[data-field="activities_txt"]').forEach(function(el) {
    el.value = '';
  });
  document.querySelectorAll('.msg-input[data-field="comments"]').forEach(function(el) {
    el.value = '';
  });
  document.querySelectorAll('.act-both-cb, .act-email-cb').forEach(function(el) {
    el.checked = false;
  });

  // Attendance is an independent persisted record; form reset must preserve it.
  resetPhotoUI();

  if (!gid) return;

  openPhotoClearDayModal(function(confirmed) {
    if (!confirmed) {
      showToast('Τα πεδία καθάρισαν. Οι φωτογραφίες διατηρήθηκαν.','info');
      return;
    }

    apiPost('/api/messages/photos/purge-day', {group_id: gid, date: date, _token: CSRF}, function(err, resp) {
      if (err || !resp) {
        showToast('Τα πεδία καθάρισαν, αλλά δεν επιβεβαιώθηκε η οριστική διαγραφή φωτογραφιών. Ελέγξτε τη λίστα φωτογραφιών.','warning');
        return;
      }
      if (resp.error || resp.success !== true) {
        showToast('Τα πεδία καθάρισαν, αλλά δεν ολοκληρώθηκε η οριστική διαγραφή φωτογραφιών.' + (resp.error ? ' ' + resp.error : ''), 'warning');
        return;
      }
      var deleted = parseInt(resp.purged || 0, 10);
      var message = deleted > 0
        ? 'Τα πεδία καθάρισαν και διαγράφηκαν οριστικά ' + deleted + ' φωτογραφίες. Τα links τους δεν είναι πλέον διαθέσιμα.'
        : 'Τα πεδία καθάρισαν. Δεν υπήρχαν φωτογραφίες προς διαγραφή για το επιλεγμένο τμήμα και την ημερομηνία.';
      showToast(message, 'success');
    });
  });
}

function openPhotoClearDayModal(onDecision) {
  var modal = document.getElementById('photo-clear-day-modal');
  if (!modal) {
    if (typeof onDecision === 'function') onDecision(false);
    return;
  }

  photoClearDayResolver = typeof onDecision === 'function' ? onDecision : null;
  var group = document.getElementById('sel-group');
  var date = document.getElementById('sel-date').value;
  document.getElementById('photo-clear-day-context').textContent =
    (group.value ? group.options[group.selectedIndex].text : 'Τρέχον τμήμα') +
    (date ? ' · ' + date.split('-').reverse().join('/') : '');
  modal.showModal();
  document.getElementById('photo-clear-day-cancel').focus();
}

function closePhotoClearDayModal(confirmed) {
  var modal = document.getElementById('photo-clear-day-modal');
  if (!modal || !modal.open) return;
  modal.close();

  var resolver = photoClearDayResolver;
  photoClearDayResolver = null;
  if (typeof resolver === 'function') {
    resolver(!!confirmed);
  }
}

document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('sel-group').addEventListener('change', invalidateComposeScope);
  document.getElementById('sel-date').addEventListener('change', invalidateComposeScope);
  if (IS_ADMIN) {
    var wrap = document.getElementById('photo-show-hidden-wrap');
    if (wrap) wrap.style.display = 'block';
    var btn = document.getElementById('btn-photo-purge-day');
    if (btn) btn.style.display = 'inline-flex';
  }
});

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }

onPhotoUploadModeChange();
</script>

<style>
.rating-btn { border:1px solid #ccc; background:#f5f5f5; cursor:pointer; padding:2px 6px; font-size:11px; border-radius:3px; margin:1px; }
.rating-btn.active.rating-1 { background:#e74c3c; color:#fff; border-color:#e74c3c; }
.rating-btn.active.rating-2 { background:#e8a020; color:#fff; border-color:#e8a020; }
.rating-btn.active.rating-3 { background:#3498db; color:#fff; border-color:#3498db; }
.rating-btn.active.rating-4 { background:#27ae60; color:#fff; border-color:#27ae60; }
.msg-table td { vertical-align:top; padding:4px 6px; }
.photo-panel-bar { display:flex; flex-direction:column; gap:14px; }
.photo-panel-inputs {
  display:grid;
  grid-template-columns:minmax(280px, 340px) minmax(260px, 1fr) auto;
  gap:16px;
  align-items:end;
}
.photo-panel-filters { display:flex; flex-wrap:wrap; gap:16px; align-items:end; justify-content:flex-start; }
.photo-panel-danger-row { display:flex; justify-content:flex-start; }
.photo-panel-field { display:flex; flex-direction:column; gap:6px; min-width:0; }
.photo-panel-stack { display:flex; flex-direction:column; gap:6px; }
.photo-panel-child select,
.photo-panel-child input[type="text"],
.photo-panel-sort select { width:100%; }
.photo-panel-files input[type="file"] { display:block; max-width:100%; }
.photo-panel-action,
.photo-panel-toggle { display:flex; align-items:center; min-height:38px; }
.photo-panel-toggle-group {
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:18px;
  min-height:38px;
  padding-bottom:2px;
}
.photo-panel-toggle-group .photo-panel-toggle {
  min-height:auto;
}
.photo-panel-sort { width:220px; }
.photo-panel-bulk-actions { display:flex; flex-wrap:wrap; gap:12px; align-items:end; }

@media (max-width: 1100px) {
  .photo-panel-inputs {
    grid-template-columns:minmax(260px, 340px) minmax(220px, 1fr);
  }
  .photo-panel-upload {
    justify-self:start;
  }
}

@media (max-width: 760px) {
  .photo-panel-inputs { grid-template-columns:1fr; gap:12px; }
  .photo-panel-filters { gap:12px; }
  .photo-panel-toggle-group { gap:12px; width:100%; }
  .photo-panel-action,
  .photo-panel-toggle,
  .photo-panel-bulk-actions {
    width:100%;
  }
  .photo-panel-sort { width:100%; }
}
</style>

<script>
function openEmailPreview() {
  var modal = document.getElementById('email-preview-modal');
  var sel = document.getElementById('preview-child-select');
  sel.innerHTML = '';
  if (!childrenData || !childrenData.length) return;
  childrenData.forEach(function(r) {
    var opt = document.createElement('option');
    opt.value = r.id;
    opt.textContent = r.last_name + ' ' + r.first_name;
    sel.appendChild(opt);
  });
  modal.style.display = 'flex';
  loadEmailPreview();
}

function closeEmailPreview() {
  document.getElementById('email-preview-modal').style.display = 'none';
}

function loadEmailPreview() {
  var sel = document.getElementById('preview-child-select');
  var childId = sel ? sel.value : '';
  var gid = document.getElementById('sel-group').value;
  var date = document.getElementById('sel-date').value;
  if (!childId || !gid) return;

  var body = document.getElementById('email-preview-body');
  body.innerHTML = '<div class="text-muted text-center" style="padding:20px;">Φόρτωση...</div>';

  apiPost('/api/messages/email-preview', {child_id: childId, group_id: gid, date: date}, function(err, resp) {
    if (!resp || resp.error) {
      body.innerHTML = '<div class="alert alert-danger">' + (resp && resp.error ? esc(resp.error) : 'Σφάλμα φόρτωσης.') + '</div>';
      return;
    }
    body.innerHTML = resp.html || '<div class="text-muted">Δεν υπάρχει περιεχόμενο.</div>';
  });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    if (document.getElementById('email-preview-modal').style.display !== 'none') {
      closeEmailPreview();
    }
  }
});
</script>
