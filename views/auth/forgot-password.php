<div class="login-container">
  <div class="login-info">
    <h2><img src="<?= APP_URL ?>/public/kinderlink-mark.svg" alt="" style="height:36px;vertical-align:middle;margin-right:6px;"><?= APP_NAME_GR ?></h2>
    <p>Συμπληρώστε το όνομα χρήστη και το email του λογαριασμού σας.</p>
    <p>Αν τα στοιχεία ταιριάζουν, θα σταλεί προσωρινός κωδικός στο email σας.</p>
  </div>

  <div class="login-box">
    <div class="login-box-title">Ανάκτηση Κωδικού</div>
    <div class="login-box-body">
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php elseif (!empty($_GET['csrf_error'])): ?>
        <div class="alert alert-warning">Η φόρμα έληξε. Παρακαλώ δοκιμάστε ξανά.</div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= BASE_URL ?>/forgot-password">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((new Controller)->csrfToken()) ?>">
        <div class="login-field">
          <label for="username">Όνομα Χρήστη</label>
          <input type="text" id="username" name="username" value="<?= htmlspecialchars($form['username'] ?? '') ?>" autocomplete="username" required>
        </div>
        <div class="login-field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($form['email'] ?? '') ?>" autocomplete="email" required>
        </div>
        <button type="submit" class="btn btn-accent" style="width:100%;justify-content:center;">
          Αποστολή προσωρινού κωδικού
        </button>
      </form>

      <div class="login-links">
        <a href="<?= BASE_URL ?>/">Επιστροφή στη σύνδεση</a>
      </div>
    </div>
  </div>
</div>