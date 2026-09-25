<?php $csrf = (new Controller)->csrfToken(); ?>

<div class="section-box">
  <div class="section-title">Ορισμός Τμημάτων</div>
  <div class="section-body" style="padding:0;">
    <div style="overflow-x:auto;">
      <table class="data-table" id="groups-table">
        <thead>
          <tr>
            <th>Τμήμα</th>
            <th style="width:90px;text-align:center;">Βασικό</th>
            <th style="width:160px;text-align:center;">Ενέργειες</th>
          </tr>
        </thead>
        <tbody id="groups-tbody">
          <tr><td colspan="3" class="text-center text-muted">Φόρτωση...</td></tr>
        </tbody>
      </table>
    </div>
    <div class="table-toolbar">
      <button class="btn-tool" onclick="openGroupModal(0)">➕ Προσθήκη</button>
      <span class="pagination-info" id="groups-pagination-info"></span>
      <button class="btn-tool" onclick="groupPage(-1)">◀</button>
      <span id="groups-page-indicator" style="font-size:11px;"></span>
      <button class="btn-tool" onclick="groupPage(1)">▶</button>
    </div>
  </div>
</div>

<!-- GROUP MODAL -->
<div class="modal-overlay" id="group-modal">
  <div class="modal-box" style="max-width:420px;">
    <div class="modal-header">
      <span id="group-modal-title">Προσθήκη Τμήματος</span>
      <button class="modal-close" onclick="closeGroupModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="group-form">
        <input type="hidden" id="gf-id" name="id" value="0">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div class="form-group">
          <label>Όνομα Τμήματος <span class="required">*</span></label>
          <input type="text" id="gf-name" name="name" required maxlength="200" style="min-width:220px;">
        </div>
        <div class="form-group">
          <label>Βασικό (τρέχον έτος)</label>
          <input type="checkbox" id="gf-is-current" name="is_current" value="1">
        </div>
      </form>
      <div id="group-form-error" class="alert alert-danger hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-accent" onclick="saveGroup()">💾 Αποθήκευση</button>
      <button class="btn btn-primary" onclick="closeGroupModal()">Ακύρωση</button>
    </div>
  </div>
</div>

<script>
var groupCurrentPage = 1, groupTotalPages = 1;

function loadGroups(page) {
  page = page || groupCurrentPage;
  groupCurrentPage = page;

  document.getElementById('groups-tbody').innerHTML =
    '<tr><td colspan="3" class="text-center text-muted">Φόρτωση...</td></tr>';

  apiPost('/api/groups', {page: page, page_size: 20}, function(err, resp) {
    if (err || !resp) { showToast('Σφάλμα φόρτωσης.', 'danger'); return; }

    groupTotalPages = Math.max(1, Math.ceil(resp.total / 20));
    document.getElementById('groups-pagination-info').textContent = '1 - ' + resp.rows.length + ' από ' + resp.total;
    document.getElementById('groups-page-indicator').textContent = 'Σελ ' + page + ' από ' + groupTotalPages;

    var tbody = '';
    resp.rows.forEach(function(r) {
      tbody += '<tr>' +
        '<td>' + esc(r.name) + '</td>' +
        '<td class="text-center">' + (r.is_current == 1 ? '☑' : '☐') + '</td>' +
        '<td class="text-center">' +
          '<a href="#" class="btn btn-sm btn-primary" onclick="openGroupModal('+r.id+')">✏️ Επεξεργασία</a> ' +
          '<a href="#" class="btn btn-sm btn-danger"  onclick="deleteGroup('+r.id+');return false;">🗑️ Διαγραφή</a>' +
        '</td>' +
      '</tr>';
    });
    if (!resp.rows.length) tbody = '<tr><td colspan="3" class="text-center text-muted">Δεν υπάρχουν εγγραφές</td></tr>';
    document.getElementById('groups-tbody').innerHTML = tbody;
  });
}

function groupPage(dir) {
  var next = groupCurrentPage + dir;
  if (next < 1 || next > groupTotalPages) return;
  loadGroups(next);
}

function openGroupModal(id) {
  document.getElementById('group-form').reset();
  document.getElementById('group-form-error').classList.add('hidden');
  document.getElementById('gf-id').value = id;

  if (id > 0) {
    document.getElementById('group-modal-title').textContent = 'Επεξεργασία Τμήματος';
    apiPost('/api/groups', {page:1, page_size:200}, function(err, resp) {
      var g = resp && resp.rows ? resp.rows.find(function(r){return r.id==id;}) : null;
      if (!g) return;
      document.getElementById('gf-name').value           = g.name;
      document.getElementById('gf-is-current').checked  = g.is_current == 1;
    });
  } else {
    document.getElementById('group-modal-title').textContent = 'Προσθήκη Τμήματος';
  }
  document.getElementById('group-modal').classList.add('show');
}

function closeGroupModal() {
  document.getElementById('group-modal').classList.remove('show');
}

function saveGroup() {
  var form = document.getElementById('group-form');
  if (!form.reportValidity()) return;
  var data = {};
  new FormData(form).forEach(function(v,k){ data[k]=v; });
  data.is_current = form.querySelector('[name="is_current"]').checked ? '1' : '0';

  apiPost('/api/groups/save', data, function(err, resp) {
    if (err || resp.error) {
      var el = document.getElementById('group-form-error');
      el.textContent = (resp&&resp.error)||'Σφάλμα αποθήκευσης.';
      el.classList.remove('hidden'); return;
    }
    closeGroupModal(); loadGroups(groupCurrentPage); showToast('Αποθηκεύτηκε!', 'success');
  });
}

var groupDeletePending = false;
async function deleteGroup(id) {
  if (groupDeletePending) return;
  var data = {id:id,_token:CSRF_TOKEN};
  groupDeletePending = true;
  try {
    if (!await confirmDelete()) return;
    var result = await new Promise(function(resolve) {
      apiPost('/api/groups/delete', data, function(err,resp) { resolve({err:err,resp:resp}); });
    });
    if (result.err || !result.resp || result.resp.error) { showToast('Σφάλμα διαγραφής.','danger'); return; }
    loadGroups(groupCurrentPage); showToast('Διαγράφηκε.','success');
  } catch (error) {
    showToast('Σφάλμα διαγραφής.','danger');
  } finally {
    groupDeletePending = false;
  }
}

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }
loadGroups(1);
</script>
