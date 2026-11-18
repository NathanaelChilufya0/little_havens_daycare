<?php
require_once __DIR__ . '/../extra.php';

$user = require_role('nurse');

$descCol = col_or_add('incidents', ['description', 'details', 'notes'], 'TEXT NULL');
$actCol  = col_or_add('incidents', ['action_taken', 'action'], 'TEXT NULL');
$sevCol  = col_or_add('incidents', ['severity'], 'VARCHAR(20) NULL');
$notCol  = col_or_add('incidents', ['parent_notified'], 'TINYINT(1) NOT NULL DEFAULT 0');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $childId = (int) ($_POST['child_id'] ?? 0);
    $child   = q1('SELECT id, name FROM children WHERE id = ? AND active = 1', [$childId]);
    $sev     = in_array(posted('severity'), ['minor', 'moderate', 'serious'], true) ? posted('severity') : 'minor';

    if (!$child || posted('description') === '') {
        set_flash('Choose a child and describe what happened.', 'error');
    } else {
        insert_row('incidents', [
            'child_id'   => $childId,
            'nurse_id'   => $user['id'],
            'nurse_name' => $user['name'],
            $descCol     => posted('description'),
            $actCol      => posted('action_taken'),
            $sevCol      => $sev,
            $notCol      => isset($_POST['parent_notified']) ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        log_activity('Incident (' . $sev . ') recorded for ' . $child['name']);
        set_flash('Incident recorded for ' . $child['name'] . '. It now shows on the parent timeline.');
    }
    redirect('nurse/incident.php');
}

$children = q('SELECT id, name, class_name FROM children WHERE active = 1 ORDER BY name');
$recent   = q(
    'SELECT i.*, c.name AS child_name FROM incidents i JOIN children c ON c.id = i.child_id ORDER BY i.id DESC LIMIT 15'
);

page_header('Incidents and first aid');
?>

<div class="card accent">
  <h2>Record an incident</h2>
  <form method="post" class="form">
    <div class="form-grid">
      <div>
        <label for="child_id">Child</label>
        <select id="child_id" name="child_id" required>
          <option value="">Choose a child</option>
          <?php foreach ($children as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['class_name']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="severity">How serious?</label>
        <select id="severity" name="severity">
          <option value="minor">Minor (small fall, scrape)</option>
          <option value="moderate">Moderate (needs close watching)</option>
          <option value="serious">Serious (call parent and emergency help)</option>
        </select>
      </div>
    </div>
    <label for="description">What happened</label>
    <textarea id="description" name="description" required></textarea>
    <label for="action_taken">First aid / action taken</label>
    <textarea id="action_taken" name="action_taken"></textarea>
    <label class="check"><input type="checkbox" name="parent_notified" value="1"> Parent has been told</label>
    <p><button class="btn btn-danger" type="submit">Save incident</button></p>
  </form>
</div>

<div class="card">
  <h2>Recent incidents</h2>
  <?php if (!$recent): ?>
    <p class="muted">No incidents recorded.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>When</th><th>Child</th><th>Level</th><th>What happened</th><th>Action</th><th>Parent told</th></tr>
      <?php foreach ($recent as $r):
          $lvl = $r[$sevCol] ?? 'minor'; ?>
        <tr>
          <td><?= e(date('D j M, H:i', strtotime($r['created_at']))) ?></td>
          <td><?= e($r['child_name']) ?></td>
          <td><span class="badge <?= $lvl === 'serious' ? 'badge-overdue' : ($lvl === 'moderate' ? 'badge-due' : 'badge-muted') ?>"><?= e(ucfirst($lvl)) ?></span></td>
          <td><?= e($r[$descCol] ?? '') ?></td>
          <td><?= e($r[$actCol] ?? '') ?></td>
          <td><?= !empty($r[$notCol]) ? 'Yes' : 'No' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php page_footer(); ?>