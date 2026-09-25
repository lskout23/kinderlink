<?php
$ratingLabels = [0=>'-', 1=>'Καθόλου', 2=>'Μέτρια', 3=>'Καλά', 4=>'Πολύ Καλά'];
$ratingColors = [0=>'#9ca3af', 1=>'#e74c3c', 2=>'#e8a020', 3=>'#3498db', 4=>'#27ae60'];
?>

<div class="section-box">
  <div class="section-title" style="display:flex;align-items:center;gap:10px;">
    Ημερήσια Αναφορά <?= htmlspecialchars($user['name'] ?? '') ?>
    <?php if (!empty($todayCount)): ?>
      <button type="button" class="today-reports" onclick="filterToday()" title="Εμφάνιση σημερινών αναφορών">
        🆕 <?= (int)$todayCount ?> <?= (int)$todayCount === 1 ? 'νέα αναφορά σήμερα' : 'νέες αναφορές σήμερα' ?>
      </button>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/inbox" class="btn btn-secondary btn-sm" style="margin-left:auto;">💬 Μηνύματα προς δάσκαλο</a>
  </div>
  <div class="section-body">

    <?php if (empty($children)): ?>
      <div class="alert alert-danger">
        Δεν βρέθηκε παιδί συνδεδεμένο με τον λογαριασμό σας.
        Παρακαλώ επικοινωνήστε με τη διεύθυνση του σχολείου.
      </div>
    <?php else: ?>

    <div class="filter-bar" style="gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div>
        <label for="par-child" style="font-size:11px;display:block;margin-bottom:2px;">Παιδί</label>
        <select id="par-child" style="min-width:180px;" onchange="loadParentMessages()">
          <?php foreach ($children as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="par-from" style="font-size:11px;display:block;margin-bottom:2px;">Από</label>
        <input type="date" id="par-from" value="<?= date('Y-m-01') ?>">
      </div>
      <div>
        <label for="par-to" style="font-size:11px;display:block;margin-bottom:2px;">Έως</label>
        <input type="date" id="par-to" value="<?= date('Y-m-d') ?>">
      </div>
      <button class="btn btn-primary" onclick="loadParentMessages()">🔍 Εμφάνιση</button>
    </div>

    <div style="overflow-x:auto;margin-top:12px;" id="par-tables-area">
      <table class="data-table" id="par-table">
        <thead>
          <tr>
            <th>Ημερ/νία</th>
            <th>Τμήμα</th>
            <th style="width:110px;text-align:center;">Πρωινό</th>
            <th style="width:110px;text-align:center;">Μεσημ/νό</th>
            <th style="width:110px;text-align:center;">Διάθεση</th>
            <th style="width:70px;text-align:center;">Ύπνος</th>
            <th style="width:50px;text-align:center;">WC</th>
            <th>Δραστηριότητες</th>
            <th>Σχόλια</th>
          </tr>
        </thead>
        <tbody id="par-tbody">
          <tr><td colspan="9" class="text-center text-muted">Φόρτωση...</td></tr>
        </tbody>
      </table>
    </div>
    <div class="table-toolbar"><span id="par-count" class="pagination-info"></span></div>

    <?php endif; ?>
  </div>
</div>

<script>
var ratingLabels = {0:'-',1:'Καθόλου',2:'Μέτρια',3:'Καλά',4:'Πολύ Καλά'};
var ratingColors  = {0:'#9ca3af',1:'#e74c3c',2:'#e8a020',3:'#3498db',4:'#27ae60'};

function loadParentMessages() {
  var cid  = document.getElementById('par-child') ? document.getElementById('par-child').value : '';
  var from = document.getElementById('par-from').value;
  var to   = document.getElementById('par-to').value;
  if (!cid) return;
  // Restore single-table view if coming from multi-child today view.
  var mainTable = document.getElementById('par-table');
  var area = document.getElementById('par-tables-area');
  if (mainTable) mainTable.style.display = '';
  if (area) area.querySelectorAll('.child-mini-table').forEach(function(el){ el.remove(); });
  document.getElementById('par-tbody').innerHTML = '<tr><td colspan="9" class="text-center text-muted">Φόρτωση...</td></tr>';
  apiPost('/api/parent/messages', {child_id:cid, from:from, to:to}, function(err,resp) {
    if (err||!resp||!resp.rows) { showToast('Σφάλμα.','danger'); return; }
    document.getElementById('par-count').textContent = resp.rows.length + ' αναφορές';
    if (!resp.rows.length) {
      var hint = '';
      if (resp.last_message_date) {
        var d = new Date(resp.last_message_date);
        var fmt = ('0'+d.getDate()).slice(-2)+'/'+ ('0'+(d.getMonth()+1)).slice(-2) +'/'+d.getFullYear();
        hint = ' Τελευταία αναφορά: <strong>' + fmt + '</strong>.';
      }
      document.getElementById('par-tbody').innerHTML =
        '<tr><td colspan="9" class="text-center text-muted" style="padding:16px;">Δεν υπάρχουν αναφορές για αυτό το διάστημα.' + hint + '</td></tr>';
      return;
    }
    var tbody = '';
    resp.rows.forEach(function(r) {
      var isToday = r.message_date && r.message_date.substring(0,10) === new Date().toISOString().substring(0,10);
      var rowStyle = isToday ? ' style="background:#f0fdf4;outline:2px solid #6ee7b7;outline-offset:-2px;"' : '';
      tbody += '<tr' + rowStyle + '>' +
        '<td style="white-space:nowrap;">' + esc(r.message_date ? r.message_date.substring(0,10).split('-').reverse().join('/') : '') + '</td>' +
        '<td>' + esc(r.group_name||'') + '</td>' +
        ratingTd(r.breakfast) + ratingTd(r.lunch) + ratingTd(r.mood) +
        '<td class="text-center">' + (r.sleep_minutes > 0 ? r.sleep_minutes+' λ.' : '-') + '</td>' +
        '<td class="text-center">' + (r.wc ? '✅' : '☐') + '</td>' +
        '<td style="font-size:11px;">' + esc(r.activities||'') + '</td>' +
        '<td style="font-size:11px;">' + esc(r.comments||'') + '</td>' +
      '</tr>';
    });
    document.getElementById('par-tbody').innerHTML = tbody;
  });
}

function ratingTd(v) {
  var color = ratingColors[v] || '#9ca3af';
  var label = ratingLabels[v] || '-';
  var bg = v > 0 ? color + '22' : '#f3f4f6';
  return '<td class="text-center"><span style="display:inline-block;padding:3px 10px;border-radius:999px;background:'+bg+';color:'+color+';font-size:11px;font-weight:700;border:1px solid '+color+'44;white-space:nowrap;">' + esc(label) + '</span></td>';
}

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }

window.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('par-child')) loadParentMessages();
});

function filterToday() {
  var today = new Date().toISOString().substring(0,10);
  var fromEl = document.getElementById('par-from');
  var toEl = document.getElementById('par-to');
  if (fromEl) fromEl.value = today;
  if (toEl) toEl.value = today;

  // Collect all children options.
  var sel = document.getElementById('par-child');
  var allOptions = sel ? Array.from(sel.options).filter(function(o){ return o.value; }) : [];

  if (allOptions.length <= 1) {
    loadParentMessages();
    return;
  }

  // Multi-child: fetch all and combine in table.
  document.getElementById('par-tbody').innerHTML = '<tr><td colspan="9" class="text-center text-muted">Φόρτωση...</td></tr>';
  var results = {};
  var pending = allOptions.length;

  allOptions.forEach(function(opt) {
    apiPost('/api/parent/messages', {child_id: opt.value, from: today, to: today}, function(err, resp) {
      results[opt.value] = {name: opt.textContent, rows: (resp && resp.rows) ? resp.rows : []};
      pending--;
      if (pending === 0) renderAllChildrenToday(allOptions, results, today);
    });
  });
}

function renderAllChildrenToday(allOptions, results, today) {
  // Hide the main table, render separate mini-tables per child.
  var area = document.getElementById('par-tables-area');
  var mainTable = document.getElementById('par-table');
  if (mainTable) mainTable.style.display = 'none';

  // Remove old extra tables.
  area.querySelectorAll('.child-mini-table').forEach(function(el){ el.remove(); });

  var theadHtml = '<thead><tr>' +
    '<th>Ημερ/νία</th><th>Τμήμα</th>' +
    '<th style="width:110px;text-align:center;">Πρωινό</th>' +
    '<th style="width:110px;text-align:center;">Μεσημ/νό</th>' +
    '<th style="width:110px;text-align:center;">Διάθεση</th>' +
    '<th style="width:70px;text-align:center;">Ύπνος</th>' +
    '<th style="width:50px;text-align:center;">WC</th>' +
    '<th>Δραστηριότητες</th><th>Σχόλια</th>' +
  '</tr></thead>';

  var total = 0;
  allOptions.forEach(function(opt) {
    var data = results[opt.value];
    var tbody = '';
    if (!data.rows.length) {
      tbody = '<tr><td colspan="9" class="text-center text-muted" style="padding:10px;">Δεν υπάρχει αναφορά σήμερα.</td></tr>';
    } else {
      data.rows.forEach(function(r) {
        tbody += '<tr style="background:#f0fdf4;">' +
          '<td style="white-space:nowrap;">' + esc(r.message_date ? r.message_date.substring(0,10).split('-').reverse().join('/') : '') + '</td>' +
          '<td>' + esc(r.group_name||'') + '</td>' +
          ratingTd(r.breakfast) + ratingTd(r.lunch) + ratingTd(r.mood) +
          '<td class="text-center">' + (r.sleep_minutes > 0 ? r.sleep_minutes+' λ.' : '-') + '</td>' +
          '<td class="text-center">' + (r.wc ? '✅' : '☐') + '</td>' +
          '<td style="font-size:11px;">' + esc(r.activities||'') + '</td>' +
          '<td style="font-size:11px;">' + esc(r.comments||'') + '</td>' +
        '</tr>';
        total++;
      });
    }

    var wrapper = document.createElement('div');
    wrapper.className = 'child-mini-table';
    wrapper.style.marginBottom = '16px';
    wrapper.innerHTML =
      '<div style="background:#065f46;color:#fff;font-weight:700;font-size:13px;padding:7px 12px;border-radius:6px 6px 0 0;letter-spacing:0.3px;">👤 ' + esc(data.name) + '</div>' +
      '<table class="data-table" style="margin-top:0;border-radius:0 0 6px 6px;">' + theadHtml + '<tbody>' + tbody + '</tbody></table>';
    area.appendChild(wrapper);
  });

  document.getElementById('par-count').textContent = total + ' αναφορές';
}
</script>