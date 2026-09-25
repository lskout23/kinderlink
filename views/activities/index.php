<?php $csrf = (new Controller)->csrfToken(); ?>

<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;">

  <!-- ACTIVITIES (both) -->
  <div class="section-box" style="flex:1;min-width:280px;">
    <div class="section-title">Δραστηριότητες (Email + Μήνυμα)</div>
    <div class="section-body" style="padding:0;">
      <table class="data-table" id="act-both-table">
        <thead>
          <tr>
            <th>Δραστηριότητα</th>
            <th style="width:120px;text-align:center;">Ενέργειες</th>
          </tr>
        </thead>
        <tbody id="act-both-tbody">
          <tr><td colspan="2" class="text-center text-muted">Φόρτωση...</td></tr>
        </tbody>
      </table>
      <div class="table-toolbar">
        <button class="btn-tool" onclick="openActModal(0,'both')">➕ Προσθήκη</button>
        <span class="pagination-info" id="act-both-info"></span>
      </div>
    </div>
  </div>

  <!-- OBSERVATIONS (email_only) -->
  <div class="section-box" style="flex:1;min-width:280px;">
    <div class="section-title">Παρατηρήσεις (Μόνο Email)</div>
    <div class="section-body" style="padding:0;">
      <table class="data-table" id="act-email-table">
        <thead>
          <tr>
            <th>Παρατήρηση</th>
            <th style="width:120px;text-align:center;">Ενέργειες</th>
          </tr>
        </thead>
        <tbody id="act-email-tbody">
          <tr><td colspan="2" class="text-center text-muted">Φόρτωση...</td></tr>
        </tbody>
      </table>
      <div class="table-toolbar">
        <button class="btn-tool" onclick="openActModal(0,'email_only')">➕ Προσθήκη</button>
        <span class="pagination-info" id="act-email-info"></span>
      </div>
    </div>
  </div>

</div>

<!-- ACTIVITY MODAL -->
<div class="modal-overlay" id="act-modal">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-header">
      <span id="act-modal-title">Προσθήκη</span>
      <button class="modal-close" onclick="closeActModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="act-form">
        <input type="hidden" id="af-id"   name="id"   value="0">
        <input type="hidden" id="af-type" name="type" value="both">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div class="form-group">
          <label>Όνομα <span class="required">*</span></label>
          <input type="text" id="af-name" name="name" required maxlength="200" style="min-width:220px;">
        </div>
      </form>
      <div id="act-form-error" class="alert alert-danger hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-accent" onclick="saveActivity()">💾 Αποθήκευση</button>
      <button class="btn btn-primary" onclick="closeActModal()">Ακύρωση</button>
    </div>
  </div>
</div>

<script>
function loadActivities(type) {
  var tbodyId = type === 'both' ? 'act-both-tbody' : 'act-email-tbody';
  var infoId  = type === 'both' ? 'act-both-info'  : 'act-email-info';

  document.getElementById(tbodyId).innerHTML = '<tr><td colspan="2" class="text-center text-muted">Φόρτωση...</td></tr>';

  apiPost('/api/activities', {type: type, page:1, page_size:100}, function(err, resp) {
    if (err) { showToast('Σφάλμα φόρτωσης.', 'danger'); return; }
    document.getElementById(infoId).textContent = resp.rows.length + ' εγγραφές';

    var tbody = '';
    resp.rows.forEach(function(r) {
      tbody += '<tr>' +
        '<td>' + esc(r.name) + '</td>' +
        '<td class="text-center">' +
          '<a href="#" class="btn btn-sm btn-primary" onclick="openActModal('+r.id+',\''+r.type+'\')">✏️</a> ' +
          '<a href="#" class="btn btn-sm btn-danger"  onclick="deleteActivity('+r.id+',\''+r.type+'\');return false;">🗑️</a>' +
        '</td>' +
      '</tr>';
    });
    if (!resp.rows.length) tbody = '<tr><td colspan="2" class="text-center text-muted">Δεν υπάρχουν εγγραφές</td></tr>';
    document.getElementById(tbodyId).innerHTML = tbody;
  });
}

function openActModal(id, type) {
  document.getElementById('act-form').reset();
  document.getElementById('act-form-error').classList.add('hidden');
  document.getElementById('af-id').value   = id;
  document.getElementById('af-type').value = type;
  document.getElementById('act-modal-title').textContent = (id>0 ? 'Επεξεργασία' : 'Προσθήκη') +
    (type==='both' ? ' Δραστηριότητας' : ' Παρατήρησης');

  if (id > 0) {
    var tbodyId = type === 'both' ? 'act-both-tbody' : 'act-email-tbody';
    var row = document.querySelector('#'+tbodyId+' tr[data-id="'+id+'"]');
    apiPost('/api/activities', {type: type, page:1, page_size:200}, function(err, resp) {
      var item = resp && resp.rows ? resp.rows.find(function(r){return r.id==id;}) : null;
      if (item) document.getElementById('af-name').value = item.name;
    });
  }

  document.getElementById('act-modal').classList.add('show');
}

function closeActModal() { document.getElementById('act-modal').classList.remove('show'); }

function saveActivity() {
  var form = document.getElementById('act-form');
  if (!form.reportValidity()) return;
  var data = {};
  new FormData(form).forEach(function(v,k){ data[k]=v; });

  apiPost('/api/activities/save', data, function(err, resp) {
    if (err || resp.error) {
      var el = document.getElementById('act-form-error');
      el.textContent = (resp&&resp.error)||'Σφάλμα αποθήκευσης.';
      el.classList.remove('hidden'); return;
    }
    var type = document.getElementById('af-type').value;
    closeActModal(); loadActivities(type); showToast('Αποθηκεύτηκε!', 'success');
  });
}

var activityDeletePending = false;
async function deleteActivity(id, type) {
  if (activityDeletePending) return;
  var data = {id:id,_token:CSRF_TOKEN};
  activityDeletePending = true;
  try {
    if (!await confirmDelete()) return;
    var result = await new Promise(function(resolve) {
      apiPost('/api/activities/delete', data, function(err,resp) { resolve({err:err,resp:resp}); });
    });
    if (result.err || !result.resp || result.resp.error) { showToast('Σφάλμα διαγραφής.','danger'); return; }
    loadActivities(type); showToast('Διαγράφηκε.','success');
  } catch (error) {
    showToast('Σφάλμα διαγραφής.','danger');
  } finally {
    activityDeletePending = false;
  }
}

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }

loadActivities('both');
loadActivities('email_only');
</script>
