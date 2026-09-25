<div class="login-container">
  <!-- Info panel (left) -->
  <div class="login-info">
    <img class="login-mascot" src="<?= APP_URL ?>/public/kinderlink-mark.svg" alt="KinderLink">
    <span class="eyebrow">ΣΧΟΛΕΙΟ & ΟΙΚΟΓΕΝΕΙΑ</span>
    <h1>Σχολείο και οικογένεια,<br>κάθε μέρα πιο κοντά.</h1>
    <p>Η καθημερινότητα των παιδιών στο σχολείο, η ενημέρωση και η επικοινωνία με τους γονείς. Όλα σε έναν κοινό χώρο.</p>
    <div class="login-features"><span>Ημερήσιες δραστηριότητες</span><span>Μηνύματα & ενημερώσεις</span></div>
  </div>

  <!-- Login box (right) -->
  <div class="login-box">
    <div class="login-box-title"><span class="eyebrow">ΚΑΛΩΣ ΗΡΘΑΤΕ</span><h2>Σύνδεση στον λογαριασμό σας</h2><p>Συμπληρώστε τα στοιχεία σας για να συνεχίσετε.</p></div>
    <div class="login-box-body">
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php elseif (!empty($_GET['csrf_error'])): ?>
        <div class="alert alert-warning">Η φόρμα έληξε. Παρακαλώ δοκιμάστε ξανά.</div>
      <?php endif; ?>

      <form method="POST" action="<?= BASE_URL ?>/">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((new Controller)->csrfToken()) ?>">
        <div class="login-field">
          <label for="username">Όνομα Χρήστη</label>
          <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required>
        </div>
        <div class="login-field">
          <label for="password">Κωδικός</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-accent" style="width:100%;justify-content:center;">
          Σύνδεση →
        </button>
      </form>

      <div class="login-links">
        <a href="<?= BASE_URL ?>/forgot-password">Ξεχάσατε τον κωδικό σας;</a>
        <a href="<?= BASE_URL ?>/forgot-username">Ξεχάσατε το όνομα χρήστη;</a>
      </div>
    </div>
  </div>
</div>
