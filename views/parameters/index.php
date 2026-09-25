<?php if (!empty($_GET['saved'])): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
  if (typeof showToast === 'function') {
    showToast('Οι παράμετροι αποθηκεύτηκαν.', 'success');
  }
  // Remove ?saved=1 from URL so re-saves always trigger the toast
  if (window.history && window.history.replaceState) {
    var url = window.location.href.replace(/[?&]saved=1/, '').replace(/\?$/, '');
    window.history.replaceState(null, '', url);
  }
});
</script>
<?php endif; ?>

<div class="section-box" style="max-width:600px;">
  <div class="section-title">Παράμετροι Εφαρμογής</div>
  <div class="section-body">
    <div class="alert" style="background:#f8fafc;border:1px solid #dbe3ec;margin-bottom:12px;display:flex;align-items:center;gap:10px;padding:8px 12px;">
      <?php if (!empty($smtpHealth['configured'])): ?>
        <span style="display:inline-block;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;">✓ SMTP Ενεργό</span>
      <?php else: ?>
        <span style="display:inline-block;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;">✗ SMTP Μη ρυθμισμένο</span>
      <?php endif; ?>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/administration/setup-parameters" data-track-changes>
      <input type="hidden" name="_token" value="<?= (new Controller)->csrfToken() ?>">

      <div class="form-group">
        <label>Όνομα Σχολείου</label>
        <input type="text" name="school_name" value="<?= htmlspecialchars($params['school_name'] ?? '') ?>" style="min-width:300px;">
      </div>

      <div class="form-group">
        <label>Λειτουργία Αποστολής Email</label>
        <div>
          <label style="margin-right:20px;">
            <input type="radio" name="email_mode" value="real" <?= ($params['email_mode'] ?? 'virtual') === 'real' ? 'checked' : '' ?>>
            Πραγματική αποστολή (SMTP)
          </label>
          <label>
            <input type="radio" name="email_mode" value="virtual" <?= ($params['email_mode'] ?? 'virtual') === 'virtual' ? 'checked' : '' ?>>
            Εικονική (simulation - δεν αποστέλλεται)
          </label>
        </div>
        <small class="text-muted">Στην εικονική λειτουργία τα email «αποστέλλονται» χωρίς να φτάνουν στον παραλήπτη (για δοκιμές).</small>
      </div>

      <div class="form-group">
        <label>Διάρκεια Link μετά από Απόκρυψη (ημέρες)</label>
        <input type="number" name="photo_hidden_grace_days" min="1" max="60" value="<?= (int)($photoHiddenGraceDays ?? 15) ?>" style="width:120px;">
        <small class="text-muted">Πόσες ημέρες παραμένει ενεργό το link μιας κρυφής φωτογραφίας.</small>
      </div>

      <div class="form-group">
        <label>Συνολικό Retention Φωτογραφιών (ημέρες)</label>
        <input type="number" name="photo_retention_days" min="1" max="730" value="<?= (int)($photoRetentionDays ?? 180) ?>" style="width:120px;">
        <small class="text-muted">Μετά από αυτό το διάστημα οι παλιές φωτογραφίες καθαρίζονται αυτόματα.</small>
      </div>

      <div class="form-group">
        <button type="submit" class="btn btn-accent">💾 Αποθήκευση</button>
      </div>
    </form>

    <?php if (empty($attendanceReady)): ?>
    <hr style="margin:24px 0 16px;">
    <section aria-labelledby="attendance-upgrade-title">
      <h2 id="attendance-upgrade-title" style="font-size:16px;">Αποθήκευση απουσιών στη βάση</h2>
      <p id="attendance-migration-result" role="status">Απαιτείται εφάπαξ αναβάθμιση. Μέχρι τότε η αποστολή ημερήσιων email και η καταχώρηση απουσιών παραμένουν ανενεργές.</p>
      <div id="attendance-migration-controls">
        <p>Δημιουργείται νέος πίνακας χωρίς αλλαγή των υπαρχόντων μηνυμάτων. Χρειάζονται δικαιώματα δημιουργίας πίνακα. Οι παλιές επιλογές «Απών» του browser δεν μεταφέρονται αυτόματα· επανελέγξτε τις πριν από αποστολή.</p>
        <label><input type="checkbox" id="attendance-backup-confirmed"> Έχω πρόσφατο, ελεγμένο backup της βάσης.</label>
        <p><button type="button" class="btn btn-accent" id="attendance-migrate" onclick="migrateAttendance()">Ενεργοποίηση αποθήκευσης απουσιών</button></p>
      </div>
    </section>
    <?php endif; ?>
    <hr style="margin:24px 0 16px;">
    <div style="background:#fff5f5;border:1px solid #fca5a5;border-radius:8px;padding:16px;">
      <div style="font-weight:700;color:#991b1b;margin-bottom:6px;">⚠️ Ζώνη Επικίνδυνων Ενεργειών</div>
      <p style="font-size:13px;color:#7f1d1d;margin:0 0 12px;">Η παρακάτω ενέργεια είναι μη αναστρέψιμη.</p>
      <button type="button" class="btn" style="background:#dc2626;color:#fff;border-color:#dc2626;" onclick="confirmPurgePhotos()">🗑️ Διαγραφή Όλων των Φωτογραφιών</button>
      <div id="purge-result" style="margin-top:10px;font-size:13px;"></div>
    </div>
  </div>
</div>

<script>
async function migrateAttendance() {
  var button = document.getElementById('attendance-migrate');
  if (!button || button.disabled) return;
  if (!document.getElementById('attendance-backup-confirmed').checked) {
    showToast('Επιβεβαιώστε πρώτα το backup της βάσης.', 'warning');
    return;
  }
  if (!await appConfirm('Να ενεργοποιηθεί η αποθήκευση απουσιών στη βάση; Απαιτείται πρόσφατο backup. Τα υπάρχοντα μηνύματα δεν αλλάζουν.', {title: 'Αναβάθμιση βάσης', confirmLabel: 'Ενεργοποίηση'})) return;
  button.disabled = true;
  var result = document.getElementById('attendance-migration-result');
  result.textContent = 'Έλεγχος και αναβάθμιση...';
  apiPost('/api/parameters/attendance-migration', {backup_confirmed: '1'}, function(err, resp) {
    if (!err && resp && resp.success && resp.attendance_ready) {
      result.textContent = 'Ενεργή — οι απουσίες αποθηκεύονται ανά παιδί και ημερομηνία. Φορτώστε ξανά το τμήμα στη Δημιουργία Μηνυμάτων.';
      document.getElementById('attendance-migration-controls').hidden = true;
      showToast('Η αναβάθμιση ολοκληρώθηκε.', 'success');
    } else {
      result.textContent = (resp && resp.error) || 'Δεν επιβεβαιώθηκε η αναβάθμιση. Μπορείτε να επαναλάβετε τον έλεγχο με ασφάλεια.';
      button.disabled = false;
      showToast('Η ενεργοποίηση δεν επιβεβαιώθηκε.', 'danger');
    }
  });
}

async function confirmPurgePhotos() {
  var btn = document.querySelector('[onclick="confirmPurgePhotos()"]');
  if (!await appConfirm('ΠΡΟΣΟΧΗ: Θα διαγραφούν ΟΛΕΣ οι φωτογραφίες από τη βάση και το αποθηκευτικό χώρο.\n\nΗ ενέργεια είναι μη αναστρέψιμη.\n\nΕίστε σίγουροι;', {
    title: 'Διαγραφή όλων των φωτογραφιών',
    confirmLabel: 'Συνέχεια στη διαγραφή',
    danger: true
  })) return;
  if (!await appConfirm('Τελευταία επιβεβαίωση: Να διαγραφούν ΟΛΕΣ οι φωτογραφίες;', {
    title: 'Τελική επιβεβαίωση διαγραφής φωτογραφιών',
    confirmLabel: 'Οριστική διαγραφή όλων',
    danger: true
  })) return;

  if (btn) { btn.disabled = true; btn.textContent = 'Διαγραφή...'; }

  apiPost('/api/messages/photos/purge-all', {}, function(err, resp) {
    if (btn) { btn.disabled = false; btn.textContent = '🗑️ Διαγραφή Όλων των Φωτογραφιών'; }
    var el = document.getElementById('purge-result');
    if (resp && resp.success) {
      el.style.color = '#065f46';
      el.textContent = 'Διαγράφηκαν ' + (resp.deleted_records || 0) + ' εγγραφές και ' + (resp.deleted_files || 0) + ' αρχεία.';
      showToast('Οι φωτογραφίες διαγράφηκαν.', 'success');
    } else {
      el.style.color = '#dc2626';
      el.textContent = (resp && resp.error) ? resp.error : 'Σφάλμα διαγραφής.';
      showToast('Σφάλμα κατά τη διαγραφή.', 'danger');
    }
  });
}
</script>
