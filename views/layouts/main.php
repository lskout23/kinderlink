<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= APP_NAME_GR ?></title>
  <!-- PWA -->
  <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
  <meta name="theme-color" content="#087f83">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="KinderLink">
  <meta name="application-name" content="KinderLink">
  <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/public/kinderlink-mark.svg">
  <link rel="apple-touch-icon" href="<?= APP_URL ?>/public/icons/icon-192.png">
  <!-- /PWA -->
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/style.css?v=20260924-1">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/ui.css?v=20260925-1">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/ui-compact.css?v=20260922-3">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/dashboard.css?v=20260923-2">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/dialogs.css?v=20260922-1">
  <script src="<?= APP_URL ?>/public/js/dialogs.js?v=20260922-1"></script>
  <!-- Global JS — must load before view scripts -->
  <script>
  var APP_BASE = '<?= BASE_URL ?>';
  var CSRF_TOKEN = '<?= htmlspecialchars((new Controller)->csrfToken()) ?>';

  function apiPost(url, data, callback) {
    data._token = CSRF_TOKEN;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.setRequestHeader('X-CSRF-TOKEN', CSRF_TOKEN);
    xhr.onload = function() {
      try {
        callback(null, JSON.parse(xhr.responseText));
      } catch(e) {
        var raw = (xhr.responseText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        var msg = raw ? raw.substring(0, 300) : 'Ο server επέστρεψε μη έγκυρη απάντηση.';
        if (xhr.status && xhr.status >= 400) {
          msg = 'HTTP ' + xhr.status + ': ' + msg;
        }
        callback(null, {error: msg});
      }
    };
    xhr.onerror = function() { callback(null, {error: 'Σφάλμα δικτύου ή μη διαθέσιμος server.'}); };
    var params = [];
    function serialize(obj, prefix) {
      for (var k in obj) {
        if (!obj.hasOwnProperty(k)) continue;
        var key = prefix ? prefix + '[' + k + ']' : k;
        var val = obj[k];
        if (val !== null && typeof val === 'object') {
          serialize(val, key);
        } else {
          params.push(encodeURIComponent(key) + '=' + encodeURIComponent(val == null ? '' : val));
        }
      }
    }
    serialize(data, '');
    xhr.send(params.join('&'));
  }

  function apiGet(url, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', APP_BASE + url, true);
    xhr.onload = function() {
      try { callback(null, JSON.parse(xhr.responseText)); } catch(e) { callback(e, null); }
    };
    xhr.onerror = function() { callback(new Error('Network error'), null); };
    xhr.send();
  }

  function showToast(message, type) {
    type = type || 'success';
    var el = document.createElement('div');
    el.className = 'toast-msg alert alert-' + type;
    el.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:220px;box-shadow:0 2px 8px rgba(0,0,0,0.2);';
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(function() { el.remove(); }, 3000);
  }

  function confirmDelete(msg) {
    return appConfirm(msg || 'Θέλετε σίγουρα να διαγράψετε αυτή την εγγραφή;', {
      title: 'Επιβεβαίωση διαγραφής', confirmLabel: 'Διαγραφή', danger: true
    });
  }
  </script>
  <?php if (isset($extraCss)) echo $extraCss; ?>
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/kinderlink.css?v=20260924-3">
</head>
<body class="modern-ui app-page compact-ui">
<a class="skip-link" href="#app-wrapper">Μετάβαση στο περιεχόμενο</a>

<!-- SESSION EXPIRATION WARNING BANNER -->
<div id="session-warning-banner" style="display:none;background-color:#fff3cd;color:#856404;padding:15px;border-bottom:2px solid #ffc107;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
  <div style="max-width:1200px;margin:0 auto;">
    <strong>⚠️ Η συνεδρία σας έληξε. Παρακαλώ <a href="<?= BASE_URL ?>/" style="color:#0056b3;font-weight:bold;">κάντε login ξανά</a></strong>
  </div>
</div>

<!-- SESSION KEEP-ALIVE MONITOR -->
<script>
(function() {
  // Ping every 30 minutes (1800000 ms)
  var pingInterval = 30 * 60 * 1000;
  var warningBanner = document.getElementById('session-warning-banner');

  function checkSession() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', APP_BASE + '/api/auth/ping', true);
    xhr.onload = function() {
      try {
        var response = JSON.parse(xhr.responseText);
        if (!response.alive) {
          // Session expired
          showSessionExpired();
        }
      } catch (e) {
        // Parse error or unexpected response
      }
    };
    xhr.onerror = function() {
      // Network error - might be temporary, don't show warning yet
    };
    xhr.send();
  }

  function showSessionExpired() {
    if (warningBanner) {
      warningBanner.style.display = 'block';
    }
    // Disable all forms to prevent data loss
    var forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
      form.style.opacity = '0.6';
      form.style.pointerEvents = 'none';
    });
    // Disable buttons
    var buttons = document.querySelectorAll('button');
    buttons.forEach(function(btn) {
      btn.disabled = true;
    });
  }

  // Start pinging on page load
  setInterval(checkSession, pingInterval);
})();
</script>

<!-- HEADER -->
<div id="app-header">
  <button class="icon-button menu-toggle" type="button" aria-label="Άνοιγμα μενού" aria-controls="app-nav" aria-expanded="false" data-menu-toggle><span data-icon="menu" aria-hidden="true">☰</span></button>
  <div class="logo">
    <?php $logoHref = ($user['role'] ?? '') === 'parent' ? BASE_URL . '/parent/dashboard' : BASE_URL . '/dashboard'; ?>
    <a href="<?= $logoHref ?>" style="display:flex;align-items:center;gap:10px;color:inherit;text-decoration:none;">
      <img src="<?= APP_URL ?>/public/kinderlink-mark.svg" alt="KinderLink" class="brand-mascot">
      <div class="app-title"><?= APP_NAME_GR ?></div>
    </a>
  </div>
  <div class="header-context"><span class="eyebrow">KINDERLINK</span><span><?= htmlspecialchars($pageTitle ?? 'Κέντρο Ελέγχου') ?></span></div>
  <div class="user-info">
    <div class="user-avatar"><?= mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8') ?></div>
    <div>
      <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="user-role"><?= $user['role'] === 'admin' ? 'Διαχειριστής' : ($user['role'] === 'teacher' ? 'Δάσκαλος/α' : 'Γονέας') ?></div>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/logout" style="display:inline;">
      <input type="hidden" name="_token" value="<?= htmlspecialchars((new Controller)->csrfToken()) ?>">
      <button type="submit" class="logout-button" aria-label="Αποσύνδεση"><span data-icon="logout" aria-hidden="true">⏻</span><span class="logout-label">Έξοδος</span></button>
    </form>
  </div>
</div>

<!-- NAVIGATION -->
<nav id="app-nav" aria-label="Κύρια πλοήγηση" tabindex="-1">
  <div class="sidebar-brand">
    <a href="<?= $logoHref ?>"><img src="<?= APP_URL ?>/public/kinderlink-mark.svg" alt="KinderLink"><span><strong>KinderLink</strong><small>Σχολείο &amp; οικογένεια</small></span></a>
    <button class="icon-button menu-close" type="button" aria-label="Κλείσιμο μενού" data-menu-close><span data-icon="close" aria-hidden="true">×</span></button>
  </div>
  <div class="nav-caption">ΚΕΝΤΡΙΚΟ ΜΕΝΟΥ</div>
  <ul>
    <?php if ($user['role'] !== 'parent'): ?>
    <li><a href="<?= BASE_URL ?>/dashboard">Αρχική</a></li>
    <?php endif; ?>

    <?php if (in_array($user['role'], ['admin', 'teacher'])): ?>
    <li>
      <a href="#"><span>Διαχείριση</span><span class="nav-caret" aria-hidden="true">▾</span></a>
      <ul>
        <li><a href="<?= BASE_URL ?>/dashboard">Κέντρο Ελέγχου</a></li>
        <?php if ($user['role'] === 'admin'): ?>
        <li><a href="<?= BASE_URL ?>/administration/setup-children">Ορισμός Παιδιών</a></li>
        <li><a href="<?= BASE_URL ?>/administration/setup-users">Ορισμός Χρηστών</a></li>
        <li><a href="<?= BASE_URL ?>/administration/setup-groups">Ορισμός Τμημάτων</a></li>
        <li><a href="<?= BASE_URL ?>/administration/children-per-group">Παιδιά ανά Τμήμα</a></li>
        <?php endif; ?>
        <li><a href="<?= BASE_URL ?>/administration/setup-activities">Ορισμός Δραστηριοτήτων, Παρατηρήσεων</a></li>
        <?php if ($user['role'] === 'admin'): ?>
        <li><a href="<?= BASE_URL ?>/administration/setup-parameters">Ορισμός Παραμέτρων</a></li>
        <li><a href="<?= BASE_URL ?>/administration/email-template">Πρότυπο email</a></li>
        <?php endif; ?>
        <li class="separator"></li>
        <li><a href="<?= BASE_URL ?>/administration/personal-details">Προσωπικές Ρυθμίσεις</a></li>
      </ul>
    </li>
    <li>
      <a href="#"><span>Μηνύματα</span><span class="nav-caret" aria-hidden="true">▾</span></a>
      <ul>
        <li><a href="<?= BASE_URL ?>/messages/create-messages">Δημιουργία Μηνυμάτων</a></li>
        <li><a href="<?= BASE_URL ?>/messages/message-list">Λίστα Μηνυμάτων</a></li>
        <li><a href="<?= BASE_URL ?>/messages/free-email">Ελεύθερο Email</a></li>
        <li class="separator"></li>
        <?php
          $inboxUnreadStaff = 0;
          try { $inboxUnreadStaff = (new InboxController)->countUnread($user); } catch (Throwable $e) {}
        ?>
        <li>
          <a href="<?= BASE_URL ?>/inbox">
            Μηνύματα Γονέων<?= $inboxUnreadStaff > 0 ? ' <span style="background:#e74c3c;color:#fff;border-radius:10px;padding:0 6px;font-size:11px;">' . $inboxUnreadStaff . '</span>' : '' ?>
          </a>
        </li>
      </ul>
    </li>
    <?php if ($user['role'] === 'admin'): ?>
    <li>
      <a href="#"><span>Οικονομικά</span><span class="nav-caret" aria-hidden="true">▾</span></a>
      <ul>
        <li><a href="<?= BASE_URL ?>/financial/income">Έσοδα ανά παιδί</a></li>
        <li><a href="<?= BASE_URL ?>/financial/income-totals">Έσοδα Συγκεντρωτικά</a></li>
        <li><a href="<?= BASE_URL ?>/financial/setup-activities">Ορισμός Δραστηριοτήτων</a></li>
        <li><a href="<?= BASE_URL ?>/financial/expenses">Έξοδα</a></li>
      </ul>
    </li>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($user['role'] === 'parent'): ?>
    <?php
      $inboxUnread = 0;
      try { $inboxUnread = (new InboxController)->countUnread($user); } catch (Throwable $e) {}
    ?>
    <li><a href="<?= BASE_URL ?>/parent/dashboard">Δραστηριότητες Παιδιού</a></li>
    <li>
      <a href="<?= BASE_URL ?>/inbox">
        Εισερχόμενα<?= $inboxUnread > 0 ? ' <span style="background:#e74c3c;color:#fff;border-radius:10px;padding:0 6px;font-size:11px;">' . $inboxUnread . '</span>' : '' ?>
      </a>
    </li>
    <li><a href="<?= BASE_URL ?>/parent/personal-details">Προσωπικές Ρυθμίσεις</a></li>
    <?php endif; ?>
  </ul>
</nav>
<div class="nav-backdrop" data-menu-close aria-hidden="true"></div>

<nav class="mobile-tabs" aria-label="Γρήγορη πλοήγηση">
  <?php if ($user['role'] === 'parent'): ?>
  <a href="<?= BASE_URL ?>/parent/dashboard"><span data-icon="home" aria-hidden="true"></span><span>Δραστηριότητες</span></a>
  <a href="<?= BASE_URL ?>/inbox"><span data-icon="mail" aria-hidden="true"></span><span>Εισερχόμενα<?php if ($inboxUnread > 0): ?> <span class="nav-badge"><?= (int)$inboxUnread ?></span><?php endif; ?></span></a>
  <a href="<?= BASE_URL ?>/parent/personal-details"><span data-icon="user" aria-hidden="true"></span><span>Προφίλ</span></a>
  <?php else: ?>
  <a href="<?= BASE_URL ?>/dashboard"><span data-icon="home" aria-hidden="true"></span><span>Αρχική</span></a>
  <a href="<?= BASE_URL ?>/messages/message-list" data-active-prefix="/messages/"><span data-icon="mail" aria-hidden="true"></span><span>Μηνύματα</span></a>
  <button type="button" aria-controls="app-nav" aria-expanded="false" data-menu-toggle><span data-icon="menu" aria-hidden="true"></span><span>Περισσότερα</span></button>
  <?php endif; ?>
</nav>

<!-- MAIN CONTENT -->
<main id="app-wrapper" tabindex="-1">
  <?php echo $content; ?>
</main>

<!-- FOOTER -->
<div id="app-footer">
  © <?= date('Y') ?> <?= APP_NAME_GR ?>
</div>

<script>
// Unsaved changes warning: mark forms with data-track-changes to enable.
(function() {
  var dirty = false;
  var trackingEnabled = false;

  // Delay tracking start so browser autofill/restore doesn't trigger false positive.
  window.addEventListener('load', function() {
    setTimeout(function() { trackingEnabled = true; }, 600);
  });

  document.addEventListener('input', function(e) {
    if (!trackingEnabled) return;
    if (e.isTrusted === false) return;
    if (e.target && e.target.closest && e.target.closest('form[data-track-changes]')) dirty = true;
  });
  document.addEventListener('change', function(e) {
    if (!trackingEnabled) return;
    if (e.isTrusted === false) return;
    if (e.target && e.target.closest && e.target.closest('form[data-track-changes]')) dirty = true;
  });
  window.addEventListener('beforeunload', function(e) {
    if (!dirty) return;
    e.preventDefault();
    e.returnValue = '';
  });
  document.addEventListener('submit', function(e) {
    if (e.target && e.target.closest && e.target.closest('form[data-track-changes]')) dirty = false;
  });
  window._clearUnsaved = function() { dirty = false; };
})();
</script>
<script src="<?= APP_URL ?>/public/vendor/lucide/lucide-0.468.0.min.js" defer></script>
<script src="<?= APP_URL ?>/public/js/ui.js?v=20260923-1" defer></script>
<script src="<?= APP_URL ?>/public/js/ui-compact.js?v=20260922-1" defer></script>

<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
