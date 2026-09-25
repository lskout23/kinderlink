<?php $csrf = (new Controller)->csrfToken(); ?>

<div class="section-box">
  <div class="section-title">Παιδιά ανά Τμήμα</div>
  <div class="section-body" style="padding:0;">
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:180px;">Τμήμα</th>
            <th style="width:60px;">Αρ. Παιδιών</th>
            <th>Παιδιά</th>
            <th style="width:100px;text-align:center;">Ενέργειες</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groups as $g): ?>
          <tr>
            <td style="color:<?= $g['is_current'] ? 'var(--success)' : 'var(--text-muted)' ?>;font-weight:<?= $g['is_current'] ? 'bold' : 'normal' ?>;">
              <?= htmlspecialchars($g['name']) ?>
            </td>
            <td class="text-center"><?= $g['child_count'] ?></td>
            <td style="font-size:11px;">
              <?php
              $names = array_map(fn($c) => htmlspecialchars($c['first_name'] . ' ' . $c['last_name']), $g['children']);
              echo implode(', ', $names);
              ?>
            </td>
            <td class="text-center">
              <button class="btn btn-sm btn-primary"
                      onclick="openAssignModal(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['name'])) ?>')">
                ✏️ Επεξεργασία
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ASSIGN CHILDREN MODAL -->
<div class="modal-overlay" id="assign-modal">
  <div class="modal-box" style="max-width:540px;">
    <div class="modal-header">
      <span>Παιδιά Τμήματος: <span id="assign-group-name"></span></span>
      <button class="modal-close" onclick="closeAssignModal()">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:12px;color:#666;margin-top:0;">Επιλέξτε τα παιδιά που ανήκουν σε αυτό το τμήμα:</p>
      <input type="hidden" id="assign-group-id" value="">
      <div style="columns:2;column-gap:10px;max-height:320px;overflow-y:auto;" id="assign-children-list">
        <?php foreach ($allChildren as $c): ?>
        <label style="display:block;font-size:12px;padding:2px 0;break-inside:avoid;">
          <input type="checkbox" class="assign-child-cb" value="<?= $c['id'] ?>">
          <?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name']) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-accent" onclick="saveAssignment()">💾 Αποθήκευση</button>
      <button class="btn btn-primary" onclick="closeAssignModal()">Ακύρωση</button>
    </div>
  </div>
</div>

<script>
var assignGroupChildren = <?= json_encode(
    array_reduce($groups, function($carry, $g) {
        $carry[$g['id']] = array_column($g['children'], 'id');
        return $carry;
    }, [])
) ?>;

function openAssignModal(groupId, groupName) {
  document.getElementById('assign-group-id').value = groupId;
  document.getElementById('assign-group-name').textContent = groupName;

  var assigned = assignGroupChildren[groupId] || [];
  document.querySelectorAll('.assign-child-cb').forEach(function(cb) {
    cb.checked = assigned.indexOf(parseInt(cb.value)) !== -1;
  });

  document.getElementById('assign-modal').classList.add('show');
}

function closeAssignModal() {
  document.getElementById('assign-modal').classList.remove('show');
}

function saveAssignment() {
  var groupId  = document.getElementById('assign-group-id').value;
  var selected = [];
  document.querySelectorAll('.assign-child-cb:checked').forEach(function(cb) {
    selected.push(cb.value);
  });

  apiPost('/api/groups/assign-children', {
    group_id: groupId,
    child_ids: selected,
    _token: CSRF_TOKEN
  }, function(err, resp) {
    if (err || resp.error) { showToast('Σφάλμα αποθήκευσης.', 'danger'); return; }
    closeAssignModal();
    location.reload();
  });
}
</script>
