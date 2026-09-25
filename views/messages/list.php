<?php $csrf = (new Controller)->csrfToken(); ?>

<div class="section-box">
  <div class="section-title">Λίστα Μηνυμάτων</div>
  <div class="section-body">
    <div class="filter-bar" style="gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div>
        <label for="fl-group" style="font-size:11px;display:block;margin-bottom:2px;">Τμήμα</label>
        <select id="fl-group" style="min-width:160px;">
          <option value="">Όλα τα τμήματα</option>
          <?php foreach ($groups as $g): ?>
          <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="fl-from" style="font-size:11px;display:block;margin-bottom:2px;">Από</label>
        <input type="date" id="fl-from" value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label for="fl-to" style="font-size:11px;display:block;margin-bottom:2px;">Έως</label>
        <input type="date" id="fl-to" value="<?= date('Y-m-d') ?>">
      </div>
      <button class="btn btn-primary" onclick="searchMessages()">🔍 Αναζήτηση</button>
    </div>
  </div>
</div>

<div class="section-box" style="margin-top:12px;">
  <div class="section-body" style="padding:0;">
    <div style="overflow-x:auto;">
      <table class="data-table" id="msg-list-table">
        <thead>
          <tr>
            <?php if (Auth::isAdmin()): ?>
            <th style="width:40px;text-align:center;"><input type="checkbox" id="msg-select-all" aria-label="Επιλογή όλων των μηνυμάτων" onchange="toggleSelectAll(this)"></th>
            <?php endif; ?>
            <th>Ημερ/νία</th>
            <th>Παιδί</th>
            <th>Τμήμα</th>
            <th style="width:80px;text-align:center;">Πρωινό</th>
            <th style="width:80px;text-align:center;">Μεσημεριανό</th>
            <th style="width:80px;text-align:center;">Διάθεση</th>
            <th style="width:70px;text-align:center;">Ύπνος</th>
            <th style="width:50px;text-align:center;">WC</th>
            <th>Δραστηριότητες</th>
            <th>Σχόλια</th>
            <th style="width:70px;text-align:center;">Email</th>
            <?php if (Auth::isAdmin()): ?>
            <th style="width:60px;text-align:center;">Διαγραφή</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody id="msg-list-tbody">
          <tr><td colspan="12" class="text-center text-muted">Χρησιμοποιήστε τα φίλτρα για αναζήτηση.</td></tr>
        </tbody>
      </table>
    </div>
    <div class="table-toolbar">
      <span id="msg-list-count" class="pagination-info"></span>
      <?php if (Auth::isAdmin()): ?>
      <button id="bulk-delete-btn" class="btn btn-danger" onclick="deleteBulkMessages()" style="display:none;">🗑️ Διαγραφή επιλεγμένων</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
var ratingLabels = {0:'-',1:'Καθόλου',2:'Μέτρια',3:'Καλά',4:'Πολύ Καλά'};
var ratingColors  = {'0':'','1':'#e74c3c','2':'#e8a020','3':'#3498db','4':'#27ae60'};
var isAdmin = <?= Auth::isAdmin() ? 'true' : 'false' ?>;

function searchMessages() {
  var gid  = document.getElementById('fl-group').value;
  var from = document.getElementById('fl-from').value;
  var to   = document.getElementById('fl-to').value;

  document.getElementById('msg-list-tbody').innerHTML = '<tr><td colspan="12" class="text-center text-muted">Φόρτωση...</td></tr>';

  apiPost('/api/messages/search', {group_id:gid, from:from, to:to}, function(err, resp) {
    if (err||!resp||!resp.rows) { showToast('Σφάλμα.','danger'); return; }
    document.getElementById('msg-list-count').textContent = resp.rows.length + ' εγγραφές';

    var statusLabel = {pending:'Εκκρεμεί',sent:'Εστάλη',failed:'Αποτυχία',virtual:'Εικονική αποστολή'};
    var tbody = '';
    resp.rows.forEach(function(r) {
      var rColor = function(v) { return ratingColors[v] || ''; };
      var checkboxCol = isAdmin ? '<td style="text-align:center;"><input type="checkbox" class="msg-select-item" aria-label="Επιλογή μηνύματος" value="'+r.id+'" onchange="updateBulkDeleteBtn()"></td>' : '';
      tbody += '<tr>' +
        checkboxCol +
        '<td style="white-space:nowrap;">' + esc(r.msg_date) + '</td>' +
        '<td style="white-space:nowrap;font-weight:bold;">' + esc(r.last_name+' '+r.first_name) + '</td>' +
        '<td>' + esc(r.group_name) + '</td>' +
        '<td class="text-center" style="color:'+rColor(r.breakfast)+'">' + (ratingLabels[r.breakfast]||'-') + '</td>' +
        '<td class="text-center" style="color:'+rColor(r.lunch)+'">'    + (ratingLabels[r.lunch]    ||'-') + '</td>' +
        '<td class="text-center" style="color:'+rColor(r.mood)+'">'     + (ratingLabels[r.mood]     ||'-') + '</td>' +
        '<td class="text-center">' + (r.sleep_minutes||'-') + '</td>' +
        '<td class="text-center">' + (r.wc ? '✅' : '☐') + '</td>' +
        '<td style="font-size:11px;">' + esc(r.activities||'') + '</td>' +
        '<td style="font-size:11px;">' + esc(r.comments||'') + '</td>' +
        '<td class="text-center"><span class="status-pill status-' + (Object.prototype.hasOwnProperty.call(statusLabel, r.email_status) ? r.email_status : 'unknown') + '">' + esc(statusLabel[r.email_status] || 'Άγνωστη') + '</span></td>' +
        (isAdmin ? '<td class="text-center"><button class="btn btn-sm btn-danger" aria-label="Διαγραφή μηνύματος" onclick="deleteMsg('+r.id+')">🗑️</button></td>' : '') +
      '</tr>';
    });
    if (!resp.rows.length) tbody = '<tr><td colspan="12" class="text-center text-muted">Δεν βρέθηκαν εγγραφές.</td></tr>';
    document.getElementById('msg-list-tbody').innerHTML = tbody;
  });
}

var messageDeletePending = false;
async function deleteMsg(id) {
  if (messageDeletePending) return;
  var data = {id:id,_token:CSRF_TOKEN};
  messageDeletePending = true;
  try {
    if (!await confirmDelete()) return;
    var result = await new Promise(function(resolve) {
      apiPost('/api/messages/delete', data, function(err,resp) { resolve({err:err,resp:resp}); });
    });
    if (result.err || !result.resp || result.resp.error) { showToast('Σφάλμα.','danger'); return; }
    searchMessages(); showToast('Διαγράφηκε.','success');
  } catch (error) {
    showToast('Σφάλμα.','danger');
  } finally {
    messageDeletePending = false;
  }
}

function toggleSelectAll(checkbox) {
  var checkboxes = document.querySelectorAll('.msg-select-item');
  checkboxes.forEach(function(cb) { cb.checked = checkbox.checked; });
  updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
  if (!isAdmin) return;
  var checked = document.querySelectorAll('.msg-select-item:checked').length;
  var btn = document.getElementById('bulk-delete-btn');
  btn.style.display = checked > 0 ? 'inline-block' : 'none';
  btn.textContent = '🗑️ Διαγραφή επιλεγμένων (' + checked + ')';
}

async function deleteBulkMessages() {
  if (messageDeletePending) return;
  var checked = document.querySelectorAll('.msg-select-item:checked');
  if (checked.length === 0) { showToast('Δεν έχουν επιλεγεί μηνύματα.','warning'); return; }
  var ids = Array.from(checked).map(cb => cb.value).join(',');
  var data = {ids:ids,_token:CSRF_TOKEN};
  var btn = document.getElementById('bulk-delete-btn');
  var wasDisabled = btn.disabled;
  messageDeletePending = true;
  btn.disabled = true;
  try {
    if (!await confirmDelete('Είστε σίγουρος ότι θέλετε να διαγράψετε ' + checked.length + ' μηνύματα;')) return;
    var result = await new Promise(function(resolve) {
      apiPost('/api/messages/deleteBulk', data, function(err,resp) { resolve({err:err,resp:resp}); });
    });
    var resp = result.resp;
    if (result.err || !resp || resp.error) { showToast('Σφάλμα.','danger'); return; }
    document.getElementById('msg-select-all').checked = false;
    searchMessages();
    showToast('Διαγράφηκαν ' + resp.deleted + ' μηνύματα.','success');
  } catch (error) {
    showToast('Σφάλμα.','danger');
  } finally {
    messageDeletePending = false;
    btn.disabled = wasDisabled;
  }
}

function esc(str){ var d=document.createElement('div'); d.appendChild(document.createTextNode(str)); return d.innerHTML; }
</script>
