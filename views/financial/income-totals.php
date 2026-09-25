<?php
// Income Totals view
?>
<div class="section-box">
  <div class="section-title">Σύνολα Εσόδων</div>
  <div class="section-body">
    <div class="filter-bar" style="gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div>
        <label style="font-size:11px;display:block;margin-bottom:2px;">Από</label>
        <input type="date" id="tot-from" value="<?= date('Y-m-01') ?>">
      </div>
      <div>
        <label style="font-size:11px;display:block;margin-bottom:2px;">Έως</label>
        <input type="date" id="tot-to" value="<?= date('Y-m-d') ?>">
      </div>
      <button class="btn btn-primary" onclick="loadTotals()">🔍 Αναζήτηση</button>
    </div>
  </div>
</div>

<div class="section-box" style="margin-top:12px;">
  <div class="section-body" style="padding:0;">
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr>
          <th>Παιδί</th>
          <th style="text-align:right;">Χρεώθηκε</th>
          <th style="text-align:right;">Πληρώθηκε</th>
          <th style="text-align:right;">Υπόλοιπο</th>
        </tr></thead>
        <tbody id="totals-tbody">
          <tr><td colspan="4" class="text-center text-muted">Επιλέξτε ημερομηνίες και πατήστε Αναζήτηση.</td></tr>
        </tbody>
        <tfoot id="totals-tfoot"></tfoot>
      </table>
    </div>
    <div class="table-toolbar"><span id="totals-count" class="pagination-info"></span></div>
  </div>
</div>

<script>
function loadTotals() {
  var from = document.getElementById('tot-from').value;
  var to   = document.getElementById('tot-to').value;

  document.getElementById('totals-tbody').innerHTML = '<tr><td colspan="4" class="text-center text-muted">Φόρτωση...</td></tr>';

  apiPost('/api/financial/income-totals-list', {from:from, to:to}, function(err,resp) {
    if (err||!resp||!resp.rows) { showToast('Σφάλμα.','danger'); return; }
    document.getElementById('totals-count').textContent = resp.rows.length + ' παιδιά';

    var totalCharged=0, totalPaid=0, totalBalance=0;
    var tbody = '';
    resp.rows.forEach(function(r) {
      var balance = parseFloat(r.balance);
      var balColor = balance > 0 ? 'color:#e74c3c;' : 'color:#27ae60;';
      totalCharged  += parseFloat(r.total_charged);
      totalPaid     += parseFloat(r.total_paid);
      totalBalance  += balance;
      tbody += '<tr>' +
        '<td style="font-weight:bold;">' + esc(r.last_name+' '+r.first_name) + '</td>' +
        '<td style="text-align:right;">' + parseFloat(r.total_charged).toFixed(2) + ' €</td>' +
        '<td style="text-align:right;">' + parseFloat(r.total_paid).toFixed(2) + ' €</td>' +
        '<td style="text-align:right;'+balColor+'">' + balance.toFixed(2) + ' €</td>' +
      '</tr>';
    });
    if (!resp.rows.length) tbody = '<tr><td colspan="4" class="text-center text-muted">Δεν υπάρχουν εγγραφές.</td></tr>';
    document.getElementById('totals-tbody').innerHTML = tbody;

    var bColor = totalBalance > 0 ? 'color:#e74c3c;' : 'color:#27ae60;';
    document.getElementById('totals-tfoot').innerHTML = '<tr style="font-weight:bold;background:#f0f4fa;">' +
      '<td>ΣΥΝΟΛΟ</td>' +
      '<td style="text-align:right;">' + totalCharged.toFixed(2) + ' €</td>' +
      '<td style="text-align:right;">' + totalPaid.toFixed(2) + ' €</td>' +
      '<td style="text-align:right;'+bColor+'">' + totalBalance.toFixed(2) + ' €</td>' +
    '</tr>';
  });
}

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }
</script>
