<?php $csrf = (new Controller)->csrfToken(); ?>

<div class="section-box">
  <div class="section-title">Ρύθμιση Οικονομικών Δραστηριοτήτων</div>
  <div class="section-body" style="padding:0;">
    <div style="overflow-x:auto;">
      <table class="data-table" id="fa-table">
        <thead><tr>
          <th>Δραστηριότητα</th>
          <th style="width:130px;text-align:center;">Ενέργειες</th>
        </tr></thead>
        <tbody id="fa-tbody"><tr><td colspan="2" class="text-center text-muted">Φόρτωση...</td></tr></tbody>
      </table>
    </div>
    <div class="table-toolbar">
      <button class="btn-tool" onclick="openFaModal(0)">➕ Προσθήκη</button>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="fa-modal">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-header">
      <span id="fa-modal-title">Προσθήκη Δραστηριότητας</span>
      <button class="modal-close" onclick="closeFaModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="fa-form">
        <input type="hidden" id="faf-id" name="id" value="0">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div class="form-group">
          <label>Όνομα <span class="required">*</span></label>
          <input type="text" id="faf-name" name="name" required style="min-width:240px;">
        </div>
      </form>
      <div id="fa-form-error" class="alert alert-danger hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-accent" onclick="saveFa()">💾 Αποθήκευση</button>
      <button class="btn btn-primary" onclick="closeFaModal()">Ακύρωση</button>
    </div>
  </div>
</div>

<script>
var faData = [];

function loadFa() {
  apiPost('/api/financial/activities', {}, function(err,resp) {
    if (err||!resp) return;
    faData = resp.rows||[];
    var tbody = '';
    faData.forEach(function(r) {
      tbody += '<tr>' +
        '<td>' + esc(r.name) + '</td>' +
        '<td class="text-center">' +
          '<a href="#" class="btn btn-sm btn-primary" onclick="openFaModal('+r.id+')">✏️</a> ' +
          '<a href="#" class="btn btn-sm btn-danger"  onclick="deleteFa('+r.id+');return false;">🗑️</a>' +
        '</td></tr>';
    });
    if (!faData.length) tbody = '<tr><td colspan="2" class="text-center text-muted">Δεν υπάρχουν εγγραφές.</td></tr>';
    document.getElementById('fa-tbody').innerHTML = tbody;
  });
}

function openFaModal(id) {
  document.getElementById('fa-form').reset();
  document.getElementById('fa-form-error').classList.add('hidden');
  document.getElementById('faf-id').value = id;
  document.getElementById('fa-modal-title').textContent = id>0 ? 'Επεξεργασία Δραστηριότητας' : 'Προσθήκη Δραστηριότητας';
  if (id > 0) {
    var r = faData.find(function(x){return x.id==id;});
    if (r) document.getElementById('faf-name').value = r.name;
  }
  document.getElementById('fa-modal').classList.add('show');
}

function closeFaModal() { document.getElementById('fa-modal').classList.remove('show'); }

function saveFa() {
  var form = document.getElementById('fa-form');
  if (!form.reportValidity()) return;
  var data = {};
  new FormData(form).forEach(function(v,k){ data[k]=v; });
  apiPost('/api/financial/activities/save', data, function(err,resp) {
    if (err||resp.error) {
      var el = document.getElementById('fa-form-error');
      el.textContent=(resp&&resp.error)||'Σφάλμα.'; el.classList.remove('hidden'); return;
    }
    closeFaModal(); loadFa(); showToast('Αποθηκεύτηκε!','success');
  });
}

var financialActivityDeletePending = false;
async function deleteFa(id) {
  if (financialActivityDeletePending) return;
  var data = {id:id,_token:CSRF_TOKEN};
  financialActivityDeletePending = true;
  try {
    if (!await confirmDelete()) return;
    var result = await new Promise(function(resolve) {
      apiPost('/api/financial/activities/delete', data, function(err,resp) { resolve({err:err,resp:resp}); });
    });
    if (result.err || !result.resp || result.resp.error) { showToast('Σφάλμα.','danger'); return; }
    loadFa(); showToast('Διαγράφηκε.','success');
  } catch (error) {
    showToast('Σφάλμα.','danger');
  } finally {
    financialActivityDeletePending = false;
  }
}

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }
loadFa();
</script>
