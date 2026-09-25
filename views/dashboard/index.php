<?php $isAdmin = ($user['role'] === 'admin'); ?>
<div class="dashboard-page dashboard-cards">
  <div class="page-heading"><div><span class="eyebrow">Η ΚΑΘΗΜΕΡΙΝΟΤΗΤΑ ΤΟΥ ΣΧΟΛΕΙΟΥ</span><h1>Κέντρο Ελέγχου</h1><p>Η ενημέρωση ξεκινά εδώ. Όλα όσα χρειάζεστε για τη σημερινή ημέρα.</p></div></div>

  <?php if ($isAdmin): ?>
  <!-- Stats row -->
  <div class="stats-grid">
    <div class="stat-card">
      <span class="stat-icon" data-icon="children" aria-hidden="true"></span><div class="stat-value"><?= $stats['children'] ?></div>
      <div class="stat-label">Ενεργά παιδιά</div>
    </div>
    <div class="stat-card">
      <span class="stat-icon" data-icon="grid" aria-hidden="true"></span><div class="stat-value"><?= $stats['groups'] ?></div>
      <div class="stat-label">Τρέχοντα τμήματα</div>
    </div>
    <div class="stat-card">
      <span class="stat-icon" data-icon="edit" aria-hidden="true"></span><div class="stat-value"><?= $stats['messages_today'] ?></div>
      <div class="stat-label">Καταχωρήσεις σήμερα</div>
    </div>
    <div class="stat-card">
      <span class="stat-icon" data-icon="mail" aria-hidden="true"></span><div class="stat-value"><?= $stats['emails_sent_today'] ?? 0 ?></div>
      <div class="stat-label">Emails εστάλησαν</div>
    </div>
    <div class="stat-card stat-card-warm">
      <span class="stat-icon" data-icon="clock" aria-hidden="true"></span><div class="stat-value"><?= $stats['absent_today'] ?? 0 ?></div>
      <div class="stat-label">Απόντες / Εκκρεμούν</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="dashboard-sections">
  <!-- Administration section -->
  <section class="dash-section dashboard-admin" aria-labelledby="dashboard-admin-title">
    <h2 class="dash-section-title" id="dashboard-admin-title">Διαχείριση</h2>
    <div class="dash-icons">
      <?php if ($isAdmin): ?>
      <a href="<?= BASE_URL ?>/administration/setup-children" class="dash-icon-link" data-card-color="peach" data-card-icon="user">
        <span class="icon">👶</span>Παιδιά
      </a>
      <a href="<?= BASE_URL ?>/administration/setup-users" class="dash-icon-link" data-card-color="purple" data-card-icon="users">
        <span class="icon">👤</span>Χρήστες
      </a>
      <a href="<?= BASE_URL ?>/administration/setup-groups" class="dash-icon-link" data-card-color="yellow" data-card-icon="tags">
        <span class="icon">🏷️</span>Τμήματα
      </a>
      <a href="<?= BASE_URL ?>/administration/children-per-group" class="dash-icon-link" data-card-color="blue" data-card-icon="clipboard">
        <span class="icon">📋</span>Παιδιά ανά Τμήμα
      </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/administration/setup-activities" class="dash-icon-link" data-card-color="mint" data-card-icon="edit">
        <span class="icon">📝</span>Δραστηριότητες Παρατηρήσεις
      </a>
      <?php if ($isAdmin): ?>
      <a href="<?= BASE_URL ?>/administration/setup-parameters" class="dash-icon-link" data-card-color="sage" data-card-icon="settings">
        <span class="icon">⚙️</span>Παράμετροι
      </a>
      <a href="<?= BASE_URL ?>/administration/email-template" class="dash-icon-link" data-card-color="cyan" data-card-icon="mail">
        <span class="icon">📧</span>Πρότυπο email
      </a>
      <?php endif; ?>
    </div>
  </section>

  <!-- Messages section -->
  <section class="dash-section dashboard-messages" aria-labelledby="dashboard-messages-title">
    <h2 class="dash-section-title" id="dashboard-messages-title">Μηνύματα</h2>
    <div class="dash-icons">
      <a href="<?= BASE_URL ?>/messages/create-messages" class="dash-icon-link dashboard-compose-link" data-card-color="rose" data-card-icon="mail-plus">
        <span class="icon">✏️</span><strong>Δημιουργία Μηνυμάτων</strong>
      </a>
      <a href="<?= BASE_URL ?>/messages/message-list" class="dash-icon-link" data-card-color="blue" data-card-icon="list">
        <span class="icon">📨</span>Λίστα Μηνυμάτων
      </a>
      <a href="<?= BASE_URL ?>/messages/free-email" class="dash-icon-link" data-card-color="purple" data-card-icon="mail">
        <span class="icon">📢</span>Ελεύθερο Email
      </a>
    </div>
  </section>

  <?php if ($isAdmin): ?>
  <!-- Financial section -->
  <section class="dash-section dashboard-finance" aria-labelledby="dashboard-finance-title">
    <h2 class="dash-section-title" id="dashboard-finance-title">Οικονομικά</h2>
    <div class="dash-icons">
      <a href="<?= BASE_URL ?>/financial/income" class="dash-icon-link" data-card-color="mint" data-card-icon="bars">
        <span class="icon">💶</span><span class="dashboard-link-copy"><span>Έσοδα ανά παιδί</span><small>Ανάλυση</small></span>
      </a>
      <a href="<?= BASE_URL ?>/financial/income-totals" class="dash-icon-link" data-card-color="blue" data-card-icon="chart">
        <span class="icon">📊</span><span class="dashboard-link-copy"><span>Έσοδα Συγκεντρωτικά</span><small>Σύνολο</small></span>
      </a>
      <a href="<?= BASE_URL ?>/financial/setup-activities" class="dash-icon-link" data-card-color="yellow" data-card-icon="calendar">
        <span class="icon">🏷️</span><span class="dashboard-link-copy"><span>Ορισμός Δραστηριοτήτων</span><small>Τιμοκατάλογος</small></span>
      </a>
      <a href="<?= BASE_URL ?>/financial/expenses" class="dash-icon-link" data-card-color="rose" data-card-icon="minus-circle">
        <span class="icon">💸</span><span class="dashboard-link-copy"><span>Έξοδα</span><small>Καταγραφή</small></span>
      </a>
    </div>
  </section>
  <?php endif; ?>
  </div>
</div>
