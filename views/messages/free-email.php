<?php $csrf = (new Controller)->csrfToken(); ?>

<div class="section-box" style="max-width:700px;">
  <div class="section-title">Ελεύθερο Email</div>
  <div class="section-body">
    <form id="free-email-form">
      <input type="hidden" name="_token" value="<?= $csrf ?>">

      <div class="form-group">
        <label>Αποστολή σε</label>
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
          <label>
            <input type="radio" name="target" value="group" checked onchange="toggleGroup(this)">
            Συγκεκριμένο Τμήμα
          </label>
          <label>
            <input type="radio" name="target" value="all" onchange="toggleGroup(this)">
            Όλοι
          </label>
        </div>
      </div>

      <div class="form-group" id="group-select-row">
        <label>Τμήμα</label>
        <select name="group_id" id="fe-group" style="min-width:200px;">
          <option value="">-- Επιλογή --</option>
          <?php foreach ($groups as $g): ?>
          <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Θέμα <span class="required">*</span></label>
        <input type="text" name="subject" id="fe-subject" required style="min-width:400px;">
      </div>

      <div class="form-group">
        <label>Κείμενο <span class="required">*</span></label>
        <textarea name="body" id="fe-body" rows="10" style="width:100%;border:1px solid #ccc;padding:8px;" required></textarea>
      </div>

      <div id="free-email-error" class="alert alert-danger hidden"></div>

      <div class="form-group">
        <button type="button" class="btn btn-send-email" onclick="sendFreeEmail()">📧 Αποστολή Email</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleGroup(radio) {
  document.getElementById('group-select-row').style.display = radio.value === 'all' ? 'none' : '';
}

var freeEmailPending = false;
async function sendFreeEmail() {
  if (freeEmailPending) return;
  var form    = document.getElementById('free-email-form');
  var subject = document.getElementById('fe-subject').value.trim();
  var body    = document.getElementById('fe-body').value.trim();
  var target  = form.querySelector('[name="target"]:checked').value;
  var groupId = document.getElementById('fe-group').value;

  if (!subject) { showToast('Συμπληρώστε το θέμα.','warning'); return; }
  if (!body)    { showToast('Συμπληρώστε το κείμενο.','warning'); return; }
  if (target !== 'all' && !groupId) { showToast('Επιλέξτε τμήμα.','warning'); return; }

  var data = {
    _token:  CSRF_TOKEN,
    subject: subject,
    body:    body,
    group_id: target === 'all' ? '' : groupId
  };
  if (target === 'all') data.send_all = '1';

  // Keep the approved draft intact until sending finishes; restore prior states on every exit.
  var controls = Array.from(form.elements).map(function(el) { return {el:el, disabled:el.disabled}; });
  freeEmailPending = true;
  controls.forEach(function(control) { control.el.disabled = true; });
  try {
    if (!await appConfirm('Αποστολή email σε ' + (target === 'all' ? 'ΟΛΟΥΣ τους γονείς' : 'το επιλεγμένο τμήμα') + ';', {
      title: target === 'all' ? 'Αποστολή email σε όλους τους γονείς' : 'Αποστολή email σε τμήμα',
      confirmLabel: 'Αποστολή Email',
      danger: false
    })) return;
    var result = await new Promise(function(resolve) {
      apiPost('/api/messages/send-free-email', data, function(err, resp) { resolve({err:err,resp:resp}); });
    });
    var err = result.err, resp = result.resp;
    var errEl = document.getElementById('free-email-error');
    if (err || !resp || resp.error) {
      errEl.textContent = (resp&&resp.error)||'Σφάλμα αποστολής.';
      errEl.classList.remove('hidden'); return;
    }
    if (resp.mode === 'real' && resp.result === false) {
      errEl.textContent = resp.error_detail || 'Αποτυχία SMTP αποστολής.';
      errEl.classList.remove('hidden');
      return;
    }
    errEl.classList.add('hidden');
    var msg = 'Email απεστάλη σε '+resp.count+' παραλήπτη/ες' + (resp.mode==='virtual' ? ' (εικονική αποστολή)' : '') + '.';
    showToast(msg,'success');
    form.reset();
    document.getElementById('group-select-row').style.display = '';
  } catch (error) {
    showToast('Σφάλμα αποστολής.','danger');
  } finally {
    freeEmailPending = false;
    controls.forEach(function(control) { control.el.disabled = control.disabled; });
  }
}
</script>
