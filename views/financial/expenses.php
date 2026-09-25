<?php $csrf = (new Controller)->csrfToken(); ?>

<div class="section-box">
  <div class="section-title">Έξοδα</div>
  <div class="section-body">
    <div class="filter-bar" style="gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div>
        <label style="font-size:11px;display:block;margin-bottom:2px;">Από</label>
        <input type="date" id="exp-from" value="<?= date('Y-m-01') ?>">
      </div>
      <div>
        <label style="font-size:11px;display:block;margin-bottom:2px;">Έως</label>
        <input type="date" id="exp-to" value="<?= date('Y-m-d') ?>">
      </div>
      <button class="btn btn-primary" onclick="loadExpenses()">🔍 Αναζήτηση</button>
      <button class="btn btn-accent"  onclick="openExpModal(0)">➕ Νέο Έξοδο</button>
    </div>
  </div>
</div>

<div class="section-box" style="margin-top:12px;">
  <div class="section-body" style="padding:0;">
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr>
          <th>Ημερομηνία</th>
          <th>Δραστηριότητα</th>
          <th>Σημειώσεις</th>
          <th style="text-align:right;">Ποσό</th>
          <th style="width:120px;text-align:center;">Ενέργειες</th>
        </tr></thead>
        <tbody id="exp-tbody"><tr><td colspan="5" class="text-center text-muted">Πατήστε Αναζήτηση.</td></tr></tbody>
        <tfoot id="exp-tfoot"></tfoot>
      </table>
    </div>
    <div class="table-toolbar"><span id="exp-count" class="pagination-info"></span></div>
  </div>
</div>

<!-- EXPENSE MODAL -->
<div class="modal-overlay" id="exp-modal">
  <div class="modal-box" style="max-width:440px;">
    <div class="modal-header">
      <span id="exp-modal-title">Νέο Έξοδο</span>
      <button class="modal-close" onclick="closeExpModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="exp-form">
        <input type="hidden" id="ef-id" name="id" value="0">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div class="form-group">
          <label>Δραστηριότητα</label>
          <select name="activity_id" id="ef-activity">
            <option value="">--</option>
            <?php foreach ($financialActivities as $fa): ?>
            <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Ημερομηνία</label>
          <input type="date" name="entry_date" id="ef-entry-date" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Ποσό (€)</label>
          <input type="number" name="amount" id="ef-amount" min="0" step="0.01" style="width:100px;" value="0">
        </div>
        <div class="form-group">
          <label>Σημειώσεις</label>
          <textarea name="notes" id="ef-notes" rows="2" style="width:100%;"></textarea>
        </div>
      </form>
      <div id="exp-form-error" class="alert alert-danger hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-accent" onclick="saveExp()">💾 Αποθήκευση</button>
      <button class="btn btn-primary" onclick="closeExpModal()">Ακύρωση</button>
    </div>
  </div>
</div>

<script>
var expData = [];

function loadExpenses() {
  var from = document.getElementById('exp-from').value;
  var to   = document.getElementById('exp-to').value;
  document.getElementById('exp-tbody').innerHTML = '<tr><td colspan="5" class="text-center text-muted">Φόρτωση...</td></tr>';

  apiPost('/api/financial/expenses-list', {from:from, to:to}, function(err,resp) {
    if (err||!resp) { showToast('Σφάλμα.','danger'); return; }
    expData = resp.rows||[];
    document.getElementById('exp-count').textContent = expData.length+' εγγραφές';

    var total=0;
    var tbody = '';
    expData.forEach(function(r) {
      total += parseFloat(r.amount);
      tbody += '<tr>' +
        '<td>' + esc(r.entry_date) + '</td>' +
        '<td>' + esc(r.activity_name||'-') + '</td>' +
        '<td>' + esc(r.notes||'') + '</td>' +
        '<td style="text-align:right;">' + parseFloat(r.amount).toFixed(2)+' €</td>' +
        '<td class="text-center">' +
          '<a href="#" class="btn btn-sm btn-primary" onclick="openExpModal('+r.id+')">✏️</a> ' +
          '<a href="#" class="btn btn-sm btn-danger"  onclick="deleteExp('+r.id+')">🗑️</a>' +
        '</td></tr>';
    });
    if (!expData.length) tbody='<tr><td colspan="5" class="text-center text-muted">Δεν υπάρχουν εγγραφές.</td></tr>';
    document.getElementById('exp-tbody').innerHTML = tbody;
    document.getElementById('exp-tfoot').innerHTML = '<tr style="font-weight:bold;background:#f0f4fa;">' +
      '<td colspan="3">ΣΥΝΟΛΟ</td><td style="text-align:right;">'+total.toFixed(2)+' €</td><td></td></tr>';
  });
}

function openExpModal(id) {
  document.getElementById('exp-form').reset();
  document.getElementById('exp-form-error').classList.add('hidden');
  document.getElementById('ef-id').value = id;
  document.getElementById('exp-modal-title').textContent = id>0 ? 'Επεξεργασία Εξόδου' : 'Νέο Έξοδο';
  if (id > 0) {
    var r = expData.find(function(x){return x.id==id;});
    if (r) {
      document.getElementById('ef-activity').value   = r.activity_id||'';
      document.getElementById('ef-entry-date').value = r.entry_date;
      document.getElementById('ef-amount').value     = r.amount;
      document.getElementById('ef-notes').value      = r.notes||'';
    }
  }
  document.getElementById('exp-modal').classList.add('show');
}

function closeExpModal() { document.getElementById('exp-modal').classList.remove('show'); }

function saveExp() {
  var form = document.getElementById('exp-form');
  var data = {};
  new FormData(form).forEach(function(v,k){ data[k]=v; });
  apiPost('/api/financial/expenses-save', data, function(err,resp) {
    if (err||resp.error) {
      var el = document.getElementById('exp-form-error');
      el.textContent=(resp&&resp.error)||'Σφάλμα.'; el.classList.remove('hidden'); return;
    }
    closeExpModal(); loadExpenses(); showToast('Αποθηκεύτηκε!','success');
  });
}

async function deleteExp(id) {
  if (!await confirmDelete()) return;
  apiPost('/api/financial/expenses-delete', {id:id,_token:CSRF_TOKEN}, function(err,resp) {
    if (err||resp.error){ showToast('Σφάλμα.','danger'); return; }
    loadExpenses(); showToast('Διαγράφηκε.','success');
  });
}

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }
</script>
