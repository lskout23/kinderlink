<?php if (!empty($_GET['saved'])): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
  if (typeof showToast === 'function') showToast('Τα στοιχεία αποθηκεύτηκαν.', 'success');
  if (window.history && window.history.replaceState) {
    window.history.replaceState(null, '', window.location.href.replace(/[?&]saved=1/, '').replace(/\?$/, ''));
  }
});
</script>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="section-box" style="max-width:500px;">
  <div class="section-title">Προσωπικά Στοιχεία</div>
  <div class="section-body">
    <?php $personalAction = (($user['role'] ?? '') === 'parent') ? '/parent/personal-details' : '/administration/personal-details'; ?>
    <form id="personal-form" method="POST" action="<?= BASE_URL . $personalAction ?>" autocomplete="off">
      <input type="hidden" name="_token" value="<?= (new Controller)->csrfToken() ?>">
      <input type="hidden" name="form_mode" value="combined">
      <input type="text" name="fake_username" autocomplete="section-login username" tabindex="-1" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
      <input type="password" name="fake_password" autocomplete="section-login current-password" tabindex="-1" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">

      <div class="form-group">
        <label>Ονοματεπώνυμο <span class="required">*</span></label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required style="min-width:280px;">
      </div>
      <div class="form-group">
        <label>Όνομα Χρήστη <span class="required">*</span></label>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required minlength="4" maxlength="50" title="4-50 χαρακτήρες: λατινικά, αριθμοί, @, τελεία, _ ή -" autocomplete="username">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input id="pf-email" type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" data-initial-email="<?= htmlspecialchars($user['email'] ?? '') ?>" autocomplete="email" spellcheck="false">
      </div>

      <hr style="margin:16px 0;">
      <p style="font-size:12px;color:#666;margin:0 0 8px;">Αφήστε κενό αν δεν θέλετε αλλαγή κωδικού.</p>

      <div class="form-group">
        <label>Τρέχων Κωδικός</label>
        <div style="display:flex;gap:8px;align-items:center;min-width:280px;">
          <input id="pf-current-password" type="password" name="current_password" autocomplete="section-password current-password" data-lpignore="true" style="flex:1 1 auto;min-width:0;">
          <button type="button" class="btn-tool" data-toggle-password="pf-current-password" aria-label="Εμφάνιση ή απόκρυψη τρέχοντος κωδικού">Εμφάνιση</button>
        </div>
        <div id="caps-pf-current-password" style="display:none;font-size:12px;color:#8a4b00;margin-top:4px;">Προσοχή: Το Caps Lock είναι ενεργό.</div>
      </div>
      <div class="form-group">
        <label>Νέος Κωδικός</label>
        <div style="display:flex;gap:8px;align-items:center;min-width:280px;">
          <input id="pf-new-password" type="password" name="new_password" autocomplete="section-password new-password" minlength="10" style="flex:1 1 auto;min-width:0;">
          <button type="button" class="btn-tool" data-toggle-password="pf-new-password" aria-label="Εμφάνιση ή απόκρυψη νέου κωδικού">Εμφάνιση</button>
        </div>
        <div id="caps-pf-new-password" style="display:none;font-size:12px;color:#8a4b00;margin-top:4px;">Προσοχή: Το Caps Lock είναι ενεργό.</div>
      </div>
      <div class="form-group">
        <label>Επιβεβαίωση Κωδικού</label>
        <div style="display:flex;gap:8px;align-items:center;min-width:280px;">
          <input id="pf-confirm-password" type="password" name="confirm_password" autocomplete="section-password new-password" style="flex:1 1 auto;min-width:0;">
          <button type="button" class="btn-tool" data-toggle-password="pf-confirm-password" aria-label="Εμφάνιση ή απόκρυψη επιβεβαίωσης κωδικού">Εμφάνιση</button>
        </div>
        <div id="caps-pf-confirm-password" style="display:none;font-size:12px;color:#8a4b00;margin-top:4px;">Προσοχή: Το Caps Lock είναι ενεργό.</div>
      </div>

      <div class="form-group" style="margin-top:16px;">
        <button type="submit" class="btn btn-accent">💾 Αποθήκευση</button>
      </div>

      <div id="personal-form-error" class="alert alert-danger hidden"></div>
    </form>
  </div>
</div>

<script>
var PERSONAL_USERNAME_POLICY_MSG = 'Περιορισμοί username: 4-50 χαρακτήρες, μόνο λατινικά γράμματα, αριθμοί, @, τελεία (.), κάτω παύλα (_) ή παύλα (-).';
var PERSONAL_PASSWORD_POLICY_MSG = 'Περιορισμοί κωδικού: τουλάχιστον 10 χαρακτήρες, τουλάχιστον 1 μικρό, 1 κεφαλαίο, 1 αριθμός, 1 σύμβολο, χωρίς κενά.';

document.getElementById('personal-form').addEventListener('submit', function(e) {
  var form = e.currentTarget;
  var errEl = document.getElementById('personal-form-error');
  errEl.classList.add('hidden');

  var username = (form.querySelector('[name="username"]').value || '').trim();
  var currPass = form.querySelector('[name="current_password"]').value || '';
  var newPass  = form.querySelector('[name="new_password"]').value || '';
  var confPass = form.querySelector('[name="confirm_password"]').value || '';

  if (!/^[A-Za-z0-9@._-]{4,50}$/.test(username)) {
    e.preventDefault();
    errEl.textContent = 'Μη έγκυρο username. ' + PERSONAL_USERNAME_POLICY_MSG;
    errEl.classList.remove('hidden');
    return;
  }

  if (newPass.length > 0) {
    if (!currPass) {
      e.preventDefault();
      errEl.textContent = 'Για αλλαγή κωδικού απαιτείται ο τρέχων κωδικός.';
      errEl.classList.remove('hidden');
      return;
    }
    var strong = newPass.length >= 10 && !/\s/.test(newPass)
      && /[a-z]/.test(newPass) && /[A-Z]/.test(newPass)
      && /\d/.test(newPass) && /[^A-Za-z0-9]/.test(newPass);
    if (!strong) {
      e.preventDefault();
      errEl.textContent = 'Μη ασφαλής κωδικός. ' + PERSONAL_PASSWORD_POLICY_MSG;
      errEl.classList.remove('hidden');
      return;
    }
    if (newPass !== confPass) {
      e.preventDefault();
      errEl.textContent = 'Οι κωδικοί δεν ταιριάζουν.';
      errEl.classList.remove('hidden');
      return;
    }
  }
});

document.querySelectorAll('[data-toggle-password]').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var inputId = btn.getAttribute('data-toggle-password');
    var input = document.getElementById(inputId);
    if (!input) return;
    var isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.textContent = isHidden ? 'Απόκρυψη' : 'Εμφάνιση';
  });
});

function bindCapsLockHint(inputId) {
  var input = document.getElementById(inputId);
  var hint = document.getElementById('caps-' + inputId);
  if (!input || !hint) return;
  function updateHint(e) {
    if (!e || typeof e.getModifierState !== 'function') return;
    hint.style.display = e.getModifierState('CapsLock') ? 'block' : 'none';
  }
  input.addEventListener('keydown', updateHint);
  input.addEventListener('keyup', updateHint);
  input.addEventListener('focus', updateHint);
  input.addEventListener('blur', function() { hint.style.display = 'none'; });
}

bindCapsLockHint('pf-current-password');
bindCapsLockHint('pf-new-password');
bindCapsLockHint('pf-confirm-password');

window.addEventListener('pageshow', function() {
  var emailInput = document.getElementById('pf-email');
  var currentInput = document.getElementById('pf-current-password');
  var newInput = document.getElementById('pf-new-password');
  var confInput = document.getElementById('pf-confirm-password');
  if (emailInput && !emailInput.value) {
    var initialEmail = emailInput.getAttribute('data-initial-email') || '';
    if (initialEmail) emailInput.value = initialEmail;
  }
  if (currentInput) currentInput.value = '';
  if (newInput) newInput.value = '';
  if (confInput) confInput.value = '';
});
</script>
