<?php $csrf = (new Controller)->csrfToken(); ?>

<div class="section-box">
  <div class="section-title">Έσοδα ανά Παιδί</div>
  <div class="section-body">
    <div class="filter-bar" style="gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div>
        <label style="font-size:11px;display:block;margin-bottom:2px;">Παιδί</label>
        <input type="text" id="fi-child-search" placeholder="Αναζήτηση παιδιού..." oninput="filterIncomeChildren()" autocomplete="off" style="min-width:200px;margin-bottom:4px;display:block;">
        <select id="fi-child" style="min-width:200px;" onchange="loadIncomeForChild()">
          <option value="">-- Επιλογή Παιδιού --</option>
          <?php foreach ($allChildren as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['last_name'].' '.$c['first_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-accent" onclick="openIncomeModal(0)">➕ Νέα Εγγραφή</button>
    </div>
  </div>
</div>

<!-- Pending -->
<div class="section-box" style="margin-top:12px;" id="pending-box">
  <div class="section-title">Εκκρεμότητες</div>
  <div class="section-body" style="padding:0;">
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr>
          <th>Δραστηριότητα</th><th>Περιγραφή</th><th>Ημερ. Καταχ.</th>
          <th>Ημερ. Είσπ.</th><th style="text-align:right;">Ποσό</th>
          <th style="text-align:right;">Πληρωμένο</th><th style="text-align:right;">Υπόλοιπο</th>
          <th style="width:120px;text-align:center;">Ενέργειες</th>
        </tr></thead>
        <tbody id="pending-tbody"><tr><td colspan="8" class="text-center text-muted">Επιλέξτε παιδί.</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Full record (Καρτέλα) -->
<div class="section-box" style="margin-top:12px;">
  <div class="section-title">Καρτέλα</div>
  <div class="section-body" style="padding:0;">
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr>
          <th>Δραστηριότητα</th><th>Περιγραφή</th><th>Ημερ. Καταχ.</th>
          <th>Ημερ. Είσπ.</th><th style="text-align:right;">Ποσό</th>
          <th style="text-align:right;">Πληρωμένο</th><th style="text-align:right;">Υπόλοιπο</th>
          <th style="width:120px;text-align:center;">Ενέργειες</th>
        </tr></thead>
        <tbody id="full-tbody"><tr><td colspan="8" class="text-center text-muted">Επιλέξτε παιδί.</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- INCOME MODAL -->
<div class="modal-overlay" id="income-modal">
  <div class="modal-box" style="max-width:520px;">
    <div class="modal-header">
      <span id="income-modal-title">Νέα Εγγραφή Εσόδου</span>
      <button class="modal-close" onclick="closeIncomeModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="income-form">
        <input type="hidden" id="inf-id" name="id" value="0">
        <input type="hidden" name="_token" value="<?= $csrf ?>">
        <div class="form-group">
          <label>Παιδί <span class="required">*</span></label>
          <select name="child_id" id="inf-child" style="min-width:200px;">
            <?php foreach ($allChildren as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['last_name'].' '.$c['first_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Δραστηριότητα</label>
          <select name="activity_id" id="inf-activity">
            <option value="">--</option>
            <?php foreach ($financialActivities as $fa): ?>
            <option value="<?= $fa['id'] ?>"><?= htmlspecialchars($fa['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Περιγραφή</label>
          <input type="text" name="description" id="inf-description" style="min-width:280px;">
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <div class="form-group">
            <label>Ημερ. Καταχ.</label>
            <input type="date" name="entry_date" id="inf-entry-date" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label>Ημερ. Είσπ.</label>
            <input type="date" name="collection_date" id="inf-collection-date">
          </div>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <div class="form-group">
            <label>Ποσό (€) <span class="required">*</span></label>
            <input type="number" name="amount" id="inf-amount" min="0" step="0.01" style="width:100px;" value="0">
          </div>
          <div class="form-group">
            <label>Πληρωμένο (€)</label>
            <input type="number" name="amount_paid" id="inf-amount-paid" min="0" step="0.01" style="width:100px;" value="0">
          </div>
        </div>
        <div class="form-group">
          <label>Σημειώσεις</label>
          <textarea name="notes" id="inf-notes" rows="2" style="width:100%;"></textarea>
        </div>
      </form>
      <div id="income-form-error" class="alert alert-danger hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-accent" onclick="saveIncome()">💾 Αποθήκευση</button>
      <button class="btn btn-primary" onclick="closeIncomeModal()">Ακύρωση</button>
    </div>
  </div>
</div>

<script>
var CSRF = '<?= $csrf ?>';
var currentChildId = 0;

function filterIncomeChildren() {
  var query = (document.getElementById('fi-child-search').value || '').toLowerCase();
  var sel = document.getElementById('fi-child');
  Array.from(sel.options).forEach(function(opt) {
    if (!opt.value) { opt.style.display = ''; return; }
    opt.style.display = opt.text.toLowerCase().indexOf(query) !== -1 ? '' : 'none';
  });
}

function loadIncomeForChild() {
  var cid = document.getElementById('fi-child').value;
  currentChildId = cid;
  if (!cid) return;

  apiPost('/api/financial/income-list', {child_id:cid}, function(err, resp) {
    if (err||!resp) { showToast('Σφάλμα φόρτωσης.','danger'); return; }
    renderIncomeTable('pending-tbody', resp.pending);
    renderIncomeTable('full-tbody',    resp.full);
  });
}

function renderIncomeTable(tbodyId, rows) {
  if (!rows||!rows.length) {
    document.getElementById(tbodyId).innerHTML = '<tr><td colspan="8" class="text-center text-muted">Δεν υπάρχουν εγγραφές.</td></tr>';
    return;
  }
  var tbody = '';
  rows.forEach(function(r) {
    var balance = (parseFloat(r.amount)-parseFloat(r.amount_paid)).toFixed(2);
    var balColor = parseFloat(balance) > 0 ? 'color:#e74c3c;' : 'color:#27ae60;';
    tbody += '<tr>' +
      '<td>' + esc(r.activity_name||'-') + '</td>' +
      '<td>' + esc(r.description||'')    + '</td>' +
      '<td>' + esc(r.entry_date)          + '</td>' +
      '<td>' + esc(r.collection_date||'-') + '</td>' +
      '<td style="text-align:right;">' + parseFloat(r.amount).toFixed(2) + ' €</td>' +
      '<td style="text-align:right;">' + parseFloat(r.amount_paid).toFixed(2) + ' €</td>' +
      '<td style="text-align:right;'+balColor+'">' + balance + ' €</td>' +
      '<td class="text-center">' +
        '<a href="#" class="btn btn-sm btn-primary" onclick="openIncomeModal('+r.id+')">✏️</a> ' +
        '<a href="#" class="btn btn-sm btn-danger"  onclick="deleteIncome('+r.id+')">🗑️</a>' +
      '</td>' +
    '</tr>';
  });
  document.getElementById(tbodyId).innerHTML = tbody;
}

function openIncomeModal(id) {
  document.getElementById('income-form').reset();
  document.getElementById('income-form-error').classList.add('hidden');
  document.getElementById('inf-id').value = id;
  document.getElementById('income-modal-title').textContent = id>0 ? 'Επεξεργασία Εσόδου' : 'Νέα Εγγραφή Εσόδου';

  if (currentChildId) document.getElementById('inf-child').value = currentChildId;
  if (id > 0) {
    apiPost('/api/financial/income-list', {child_id: currentChildId||0}, function(err,resp) {
      var all = (resp.pending||[]).concat(resp.full||[]);
      var r = all.find(function(x){return x.id==id;});
      if (!r) return;
      document.getElementById('inf-child').value          = r.child_id;
      document.getElementById('inf-activity').value       = r.activity_id||'';
      document.getElementById('inf-description').value    = r.description||'';
      document.getElementById('inf-entry-date').value     = r.entry_date;
      document.getElementById('inf-collection-date').value= r.collection_date||'';
      document.getElementById('inf-amount').value         = r.amount;
      document.getElementById('inf-amount-paid').value    = r.amount_paid;
      document.getElementById('inf-notes').value          = r.notes||'';
    });
  }
  document.getElementById('income-modal').classList.add('show');
}

function closeIncomeModal() { document.getElementById('income-modal').classList.remove('show'); }

function saveIncome() {
  var form = document.getElementById('income-form');
  var data = {};
  new FormData(form).forEach(function(v,k){ data[k]=v; });

  apiPost('/api/financial/income-save', data, function(err,resp) {
    if (err||resp.error) {
      var el = document.getElementById('income-form-error');
      el.textContent=(resp&&resp.error)||'Σφάλμα αποθήκευσης.';
      el.classList.remove('hidden'); return;
    }
    closeIncomeModal(); loadIncomeForChild(); showToast('Αποθηκεύτηκε!','success');
  });
}

async function deleteIncome(id) {
  if (!await confirmDelete()) return;
  apiPost('/api/financial/income-delete', {id:id,_token:CSRF_TOKEN}, function(err,resp) {
    if (err||resp.error){ showToast('Σφάλμα διαγραφής.','danger'); return; }
    loadIncomeForChild(); showToast('Διαγράφηκε.','success');
  });
}

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }
</script>
