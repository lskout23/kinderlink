<?php $csrf = (new Controller)->csrfToken(); ?>
<?php
$parentOptions = array_map(function ($p) {
  return [
    'id' => (int)$p['id'],
    'label' => $p['name'] . ' (' . $p['username'] . ')',
  ];
}, $parents);
?>

<div class="section-box">
  <div class="section-title">
    Ορισμός Παιδιών
    <span id="child-count" class="text-muted" style="font-size:11px;font-weight:normal;"></span>
  </div>
  <div class="section-body" style="padding:0;">

    <!-- Search bar -->
    <div class="filter-bar" style="border-radius:0;border-left:none;border-right:none;border-top:none;">
      <label>🔍 Αναζήτηση:</label>
      <input type="text" id="child-search" placeholder="Όνομα, επώνυμο, email..." style="min-width:240px;">
      <span id="child-selected-count" class="text-muted" style="margin-left:auto;font-size:12px;">0 επιλεγμένα</span>
    </div>

    <!-- Table -->
    <div class="children-table-wrap" style="overflow-x:auto;" role="region" aria-label="Πίνακας παιδιών — οριζόντια κύλιση για όλα τα πεδία" tabindex="0">
      <table class="data-table children-table" id="children-table">
        <colgroup>
          <col class="child-select-col">
          <col class="child-name-col"><col class="child-name-col">
          <col class="child-date-col">
          <col class="child-phone-col"><col class="child-phone-col">
          <col><col><col>
          <col class="child-email-flag-col"><col class="child-email-flag-col"><col class="child-flag-col">
          <col class="child-actions-col">
        </colgroup>
        <thead>
          <tr>
            <th style="width:34px;text-align:center;"><input type="checkbox" id="child-select-all" onclick="toggleAllChildren(this.checked)"></th>
            <th>Όνομα</th>
            <th>Επώνυμο</th>
            <th>Ημ/νία Γεν.</th>
            <th>Κινητό Μητ.</th>
            <th>Κινητό Πατ.</th>
            <th>email 1</th>
            <th>email 2</th>
            <th>Parent User</th>
            <th class="child-email-heading" scope="col">Αποστολή<br>E1</th>
            <th class="child-email-heading" scope="col">Αποστολή<br>E2</th>
            <th>Ενεργό</th>
            <th class="child-actions-heading" scope="col">Ενέργειες</th>
          </tr>
        </thead>
        <tbody id="children-tbody">
          <tr><td colspan="13" class="text-center text-muted">Φόρτωση...</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Toolbar -->
    <div class="table-toolbar">
      <button class="btn-tool" onclick="openChildModal(0)">➕ Προσθήκη</button>
      <button class="btn-tool" onclick="bulkChildrenAction('clone')">Clone</button>
      <button class="btn-tool" onclick="bulkChildrenAction('activate')">Ενεργοποίηση</button>
      <button class="btn-tool" onclick="bulkChildrenAction('deactivate')">Απενεργοποίηση</button>
      <button class="btn-tool" style="background:#c0392b;color:#fff;border-color:#c0392b;" onclick="bulkChildrenAction('delete')">Διαγραφή</button>
      <span class="pagination-info" id="child-pagination-info"></span>
      <button class="btn-tool" id="child-prev-btn" onclick="childPage(-1)">◀</button>
      <span id="child-page-indicator" style="font-size:11px;"></span>
      <button class="btn-tool" id="child-next-btn" onclick="childPage(1)">▶</button>
      <select id="child-page-size" onchange="loadChildren(1)" style="font-size:11px;padding:2px;">
        <option value="16">16</option>
        <option value="30">30</option>
        <option value="50">50</option>
      </select>
    </div>

  </div>
</div>

<!-- CHILD MODAL -->
<div class="modal-overlay" id="child-modal">
  <div class="modal-box">
    <div class="modal-header">
      <span id="child-modal-title">Προσθήκη Παιδιού</span>
      <button class="modal-close" onclick="closeChildModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="child-form">
        <input type="hidden" id="cf-id" name="id" value="0">
        <input type="hidden" name="_token" value="<?= $csrf ?>">

        <div class="form-group">
          <label>Όνομα <span class="required">*</span></label>
          <input type="text" id="cf-first-name" name="first_name" required maxlength="100">
        </div>
        <div class="form-group">
          <label>Επώνυμο <span class="required">*</span></label>
          <input type="text" id="cf-last-name" name="last_name" required maxlength="100">
        </div>
        <div class="form-group">
          <label>Ημ/νία Γέννησης</label>
          <input type="date" id="cf-dob" name="dob">
        </div>
        <div class="form-group">
          <label>Κινητό Μητέρας</label>
          <input type="text" id="cf-mother-mobile" name="mother_mobile" maxlength="20">
        </div>
        <div class="form-group">
          <label>Κινητό Πατέρα</label>
          <input type="text" id="cf-father-mobile" name="father_mobile" maxlength="20">
        </div>
        <div class="form-group">
          <label>Email 1</label>
          <input type="email" id="cf-email1" name="email1" maxlength="255">
        </div>
        <div class="form-group" style="margin-left:195px;">
          <label style="min-width:auto;font-weight:normal;font-size:12px;">
            <input type="checkbox" id="cf-send-email1" name="send_email1" value="1" checked> Αποστολή σε email 1
          </label>
        </div>
        <div class="form-group">
          <label>Email 2</label>
          <input type="email" id="cf-email2" name="email2" maxlength="255">
        </div>
        <div class="form-group" style="margin-left:195px;">
          <label style="min-width:auto;font-weight:normal;font-size:12px;">
            <input type="checkbox" id="cf-send-email2" name="send_email2" value="1"> Αποστολή σε email 2
          </label>
        </div>
        <div class="form-group">
          <label>Parent User</label>
          <div style="display:flex;flex-direction:column;gap:6px;min-width:240px;">
            <input type="text" id="cf-parent-search" placeholder="Αναζήτηση parent..." oninput="filterParentOptions()" autocomplete="off">
            <select id="cf-parent-user-id" name="parent_user_id" style="min-width:240px;" onchange="syncParentSearchFromSelect();toggleParentWarning()"></select>
          </div>
        </div>
        <div id="parent-link-warning" class="alert alert-warning hidden" style="font-size:12px;margin-top:0;">
          Το παιδί θα εμφανίζεται στο Parent Portal του συγκεκριμένου λογαριασμού.
        </div>
        <div class="form-group">
          <label>Ενεργό</label>
          <input type="checkbox" id="cf-active" name="active" value="1" checked>
        </div>
      </form>
      <div id="child-form-error" class="alert alert-danger hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-accent" onclick="saveChild()">💾 Αποθήκευση</button>
      <button class="btn btn-primary" onclick="closeChildModal()">Ακύρωση</button>
    </div>
  </div>
</div>

<script>
var childCurrentPage = 1;
var childTotalPages  = 1;
var PARENT_OPTIONS = <?= json_encode($parentOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function loadChildren(page) {
  page = page || childCurrentPage;
  childCurrentPage = page;
  var search   = document.getElementById('child-search').value;
  var pageSize = document.getElementById('child-page-size').value;

  document.getElementById('children-tbody').innerHTML =
    '<tr><td colspan="13" class="text-center text-muted">Φόρτωση...</td></tr>';

  apiPost('/api/children', {page: page, page_size: pageSize, search: search}, function(err, resp) {
    if (err || !resp) { showToast('Σφάλμα φόρτωσης.', 'danger'); return; }

    var total = resp.total;
    var rows  = resp.rows;
    childTotalPages = Math.max(1, Math.ceil(total / pageSize));

    document.getElementById('child-count').textContent = '(' + total + ' εγγραφές)';
    document.getElementById('child-pagination-info').textContent =
      (rows.length ? (((page-1)*pageSize)+1) + ' - ' + (((page-1)*pageSize)+rows.length) + ' από ' + total : '0');
    document.getElementById('child-page-indicator').textContent = 'Σελ ' + page + ' από ' + childTotalPages;

    var tbody = '';
    rows.forEach(function(r) {
      tbody += '<tr>' +
        '<td class="text-center"><input type="checkbox" class="child-select" value="' + parseInt(r.id, 10) + '" onchange="updateSelectedChildrenUI()"></td>' +
        '<td>' + esc(r.first_name) + '</td>' +
        '<td>' + esc(r.last_name)  + '</td>' +
        '<td>' + (r.dob || '') + '</td>' +
        '<td>' + esc(r.mother_mobile || '') + '</td>' +
        '<td>' + esc(r.father_mobile || '') + '</td>' +
        '<td><span class="cell-ellipsis" title="' + esc(r.email1 || '') + '">' + esc(r.email1 || '') + '</span></td>' +
        '<td><span class="cell-ellipsis" title="' + esc(r.email2 || '') + '">' + esc(r.email2 || '') + '</span></td>' +
        '<td><span class="cell-ellipsis" title="' + esc(r.parent_name ? (r.parent_name + ' (' + (r.parent_username || '') + ')') : '-') + '">' + esc(r.parent_name ? (r.parent_name + ' (' + (r.parent_username || '') + ')') : '-') + '</span></td>' +
        '<td class="text-center"><input type="checkbox" disabled' + (r.send_email1 == 1 ? ' checked' : '') + '></td>' +
        '<td class="text-center"><input type="checkbox" disabled' + (r.send_email2 == 1 ? ' checked' : '') + '></td>' +
        '<td class="text-center"><input type="checkbox" disabled' + (r.active == 1 ? ' checked' : '') + '></td>' +
        '<td class="text-center child-actions" style="white-space:nowrap;">' +
          '<button type="button" class="btn btn-sm btn-primary" title="Επεξεργασία" aria-label="Επεξεργασία παιδιού" onclick="openChildModal(' + r.id + ')">✏️</button> ' +
          '<button type="button" class="btn btn-sm btn-danger" title="Διαγραφή" aria-label="Διαγραφή παιδιού" onclick="deleteChild(' + r.id + ')">🗑️</button>' +
        '</td>' +
      '</tr>';
    });
    if (!rows.length) tbody = '<tr><td colspan="13" class="text-center text-muted">Δεν υπάρχουν εγγραφές</td></tr>';
    document.getElementById('children-tbody').innerHTML = tbody;
    var selectAll = document.getElementById('child-select-all');
    if (selectAll) selectAll.checked = false;
    updateSelectedChildrenUI();
  });
}

function getSelectedChildIds() {
  var ids = [];
  document.querySelectorAll('.child-select:checked').forEach(function(el) {
    ids.push(parseInt(el.value, 10));
  });
  return ids.filter(function(id) { return id > 0; });
}

function updateSelectedChildrenUI() {
  var ids = getSelectedChildIds();
  var countEl = document.getElementById('child-selected-count');
  if (countEl) {
    countEl.textContent = ids.length + ' επιλεγμένα';
  }
  var all = document.querySelectorAll('.child-select');
  var checked = document.querySelectorAll('.child-select:checked');
  var selectAll = document.getElementById('child-select-all');
  if (selectAll) {
    selectAll.checked = all.length > 0 && all.length === checked.length;
  }
}

function toggleAllChildren(checked) {
  document.querySelectorAll('.child-select').forEach(function(el) {
    el.checked = checked;
  });
  updateSelectedChildrenUI();
}

var childrenActionPending = false;
async function bulkChildrenAction(action) {
  if (childrenActionPending) return;
  var ids = getSelectedChildIds();
  if (!ids.length) {
    showToast('Επιλέξτε πρώτα παιδιά.','warning');
    return;
  }

  var labels = {
    clone: 'Να γίνουν αντίγραφα των επιλεγμένων παιδιών;',
    activate: 'Να ενεργοποιηθούν οι επιλεγμένοι λογαριασμοί παιδιών;',
    deactivate: 'Να απενεργοποιηθούν οι επιλεγμένοι λογαριασμοί παιδιών;',
    delete: 'Να διαγραφούν οριστικά τα επιλεγμένα παιδιά;'
  };

  var titles = {
    clone: 'Αντιγραφή παιδιών',
    activate: 'Ενεργοποίηση παιδιών',
    deactivate: 'Απενεργοποίηση παιδιών',
    delete: 'Οριστική διαγραφή παιδιών'
  };
  var confirmLabels = {
    clone: 'Αντιγραφή',
    activate: 'Ενεργοποίηση',
    deactivate: 'Απενεργοποίηση',
    delete: 'Οριστική διαγραφή'
  };
  var data = {action: action, ids: ids};
  childrenActionPending = true;
  try {
    if (!await appConfirm(labels[action] || 'Να εκτελεστεί η ενέργεια;', {
      title: titles[action] || 'Μαζική ενέργεια παιδιών',
      confirmLabel: confirmLabels[action] || 'Εκτέλεση',
      danger: action === 'delete'
    })) return;
    var result = await new Promise(function(resolve) {
      apiPost('/api/children/bulk-action', data, function(err, resp) { resolve({err:err,resp:resp}); });
    });
    var err = result.err, resp = result.resp;
    if (err || !resp || resp.error) {
      showToast(resp && resp.error ? resp.error : 'Αποτυχία μαζικής ενέργειας.','danger');
      return;
    }

    var actionText = {
      clone: 'Δημιουργήθηκαν αντίγραφα για ',
      activate: 'Ενεργοποιήθηκαν ',
      deactivate: 'Απενεργοποιήθηκαν ',
      delete: 'Διαγράφηκαν '
    };
    showToast((actionText[action] || 'Ολοκληρώθηκε η ενέργεια για ') + (resp.affected || 0) + ' παιδιά.','success');
    loadChildren(childCurrentPage);
  } catch (error) {
    showToast('Αποτυχία μαζικής ενέργειας.','danger');
  } finally {
    childrenActionPending = false;
  }
}

function childPage(dir) {
  var next = childCurrentPage + dir;
  if (next < 1 || next > childTotalPages) return;
  loadChildren(next);
}

var childSearchTimer;
document.getElementById('child-search').addEventListener('input', function() {
  clearTimeout(childSearchTimer);
  childSearchTimer = setTimeout(function() { loadChildren(1); }, 350);
});

function openChildModal(id) {
  document.getElementById('child-form').reset();
  document.getElementById('child-form-error').classList.add('hidden');
  renderParentOptions('');

  if (id > 0) {
    document.getElementById('child-modal-title').textContent = 'Επεξεργασία Παιδιού';
    // Find row data from rendered table — easier to reload from API
    apiPost('/api/children', {page:1, page_size:200, search:''}, function(err, resp) {
      var child = resp && resp.rows ? resp.rows.find(function(r){return r.id==id;}) : null;
      if (!child) return;
      document.getElementById('cf-id').value           = child.id;
      document.getElementById('cf-first-name').value   = child.first_name;
      document.getElementById('cf-last-name').value    = child.last_name;
      document.getElementById('cf-dob').value          = child.dob || '';
      document.getElementById('cf-mother-mobile').value= child.mother_mobile || '';
      document.getElementById('cf-father-mobile').value= child.father_mobile || '';
      document.getElementById('cf-email1').value       = child.email1 || '';
      document.getElementById('cf-email2').value       = child.email2 || '';
      document.getElementById('cf-parent-user-id').value = child.parent_user_id || '';
      syncParentSearchFromSelect();
      document.getElementById('cf-send-email1').checked= child.send_email1 == 1;
      document.getElementById('cf-send-email2').checked= child.send_email2 == 1;
      document.getElementById('cf-active').checked     = child.active == 1;
      toggleParentWarning();
    });
  } else {
    document.getElementById('child-modal-title').textContent = 'Προσθήκη Παιδιού';
    document.getElementById('cf-id').value = '0';
    document.getElementById('cf-active').checked     = true;
    document.getElementById('cf-send-email1').checked = true;
    document.getElementById('cf-send-email2').checked = false;
    document.getElementById('cf-parent-user-id').value = '';
    document.getElementById('cf-parent-search').value = '';
    toggleParentWarning();
  }

  document.getElementById('child-modal').classList.add('show');
}

function closeChildModal() {
  document.getElementById('child-modal').classList.remove('show');
}

function saveChild() {
  var form = document.getElementById('child-form');
  if (!form.reportValidity()) return;

  var data = {};
  new FormData(form).forEach(function(v, k) { data[k] = v; });
  // Checkboxes not submitted if unchecked — handle explicitly
  ['send_email1','send_email2','active'].forEach(function(f) {
    data[f] = form.querySelector('[name="'+f+'"]').checked ? '1' : '0';
  });

  data.parent_user_id = document.getElementById('cf-parent-user-id').value || '';

  apiPost('/api/children/save', data, function(err, resp) {
    if (err || resp.error) {
      var el = document.getElementById('child-form-error');
      el.textContent = (resp && resp.error) ? resp.error : 'Σφάλμα αποθήκευσης.';
      el.classList.remove('hidden');
      return;
    }
    closeChildModal();
    loadChildren(childCurrentPage);
    showToast('Αποθηκεύτηκε!', 'success');
  });
}

async function deleteChild(id) {
  if (childrenActionPending) return;
  var data = {id: id, _token: CSRF_TOKEN};
  childrenActionPending = true;
  try {
    if (!await confirmDelete()) return;
    var result = await new Promise(function(resolve) {
      apiPost('/api/children/delete', data, function(err, resp) { resolve({err:err,resp:resp}); });
    });
    if (result.err || !result.resp || result.resp.error) { showToast('Σφάλμα διαγραφής.', 'danger'); return; }
    loadChildren(childCurrentPage);
    showToast('Διαγράφηκε.', 'success');
  } catch (error) {
    showToast('Σφάλμα διαγραφής.', 'danger');
  } finally {
    childrenActionPending = false;
  }
}

function toggleParentWarning() {
  var select = document.getElementById('cf-parent-user-id');
  var warning = document.getElementById('parent-link-warning');
  if (!select || !warning) return;

  if (select.value) {
    warning.classList.remove('hidden');
  } else {
    warning.classList.add('hidden');
  }
}

function renderParentOptions(filterText) {
  var select = document.getElementById('cf-parent-user-id');
  if (!select) return;

  var selectedValue = select.value || '';
  var filter = (filterText || '').toLowerCase();
  var html = '<option value="">-- Χωρίς σύνδεση --</option>';

  PARENT_OPTIONS.forEach(function(parent) {
    var label = parent.label || '';
    if (!filter || label.toLowerCase().indexOf(filter) !== -1) {
      html += '<option value="' + parent.id + '">' + esc(label) + '</option>';
    }
  });

  select.innerHTML = html;
  if (selectedValue) {
    select.value = selectedValue;
  }
}

function filterParentOptions() {
  var input = document.getElementById('cf-parent-search');
  renderParentOptions(input ? input.value : '');
}

function syncParentSearchFromSelect() {
  var select = document.getElementById('cf-parent-user-id');
  var input = document.getElementById('cf-parent-search');
  if (!select || !input) return;
  input.value = '';
}

function esc(str) {
  var d = document.createElement('div');
  d.appendChild(document.createTextNode(str));
  return d.innerHTML;
}

// Initial load
renderParentOptions('');
loadChildren(1);
</script>

