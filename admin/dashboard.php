<?php
require_once __DIR__ . '/../extra.php';

$user  = require_role('admin');
$today = date('Y-m-d');

$stats = [
    ['Children enrolled',        scalar('SELECT COUNT(*) FROM children WHERE active = 1')],
    ['At the centre now',        scalar('SELECT COUNT(*) FROM attendance WHERE att_date = ? AND check_in IS NOT NULL AND check_out IS NULL', [$today])],
    ['Collected today',          scalar('SELECT COUNT(*) FROM attendance WHERE att_date = ? AND check_out IS NOT NULL', [$today])],
    ['Doses given today',        scalar('SELECT COUNT(*) FROM medication_log WHERE DATE(given_at) = ?', [$today])],
    ['Centre-medicine requests', scalar("SELECT COUNT(*) FROM medications WHERE active = 1 AND nurse_status = 'pending'")],
    ['Incidents today',          scalar('SELECT COUNT(*) FROM incidents WHERE DATE(created_at) = ?', [$today])],
    ['Blocked pickups today',    scalar("SELECT COUNT(*) FROM activity_log WHERE action LIKE 'Blocked pickup%' AND DATE(created_at) = ?", [$today])],
    ['Fees outstanding',         kwacha(scalar("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'pending'"))],
    ['Fees collected',           kwacha(scalar("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'"))],
];

// Allergy conflicts between today's menu and every enrolled child.
$menu = todays_menu();
$conflicts = [];
$allergic = q(
    "SELECT c.name, h.allergies
     FROM children c JOIN health_profiles h ON h.child_id = c.id
     WHERE c.active = 1 AND h.allergies IS NOT NULL AND h.allergies <> ''
     ORDER BY c.name"
);
foreach ($allergic as $a) {
    if (is_none($a['allergies'])) {
        continue;
    }
    foreach (allergy_conflicts($a['allergies'], $menu) as $x) {
        $conflicts[] = ['child' => $a['name'], 'allergen' => $x['allergen'], 'meal' => $x['meal']];
    }
}

$attendance = q(
    'SELECT c.name AS child_name, c.class_name, a.check_in, a.dropped_by, a.check_out, a.picked_by
     FROM attendance a JOIN children c ON c.id = a.child_id
     WHERE a.att_date = ? ORDER BY a.check_in',
    [$today]
);
$recent = q('SELECT * FROM activity_log ORDER BY id DESC LIMIT 8');

page_header('Admin overview');
?>

<div class="stats">
  <?php foreach ($stats as [$label, $value]): ?>
    <div class="stat"><div class="num"><?= e($value) ?></div><div class="lbl"><?= e($label) ?></div></div>
  <?php endforeach; ?>
</div>

<?php if ($conflicts): ?>
  <div class="card accent">
    <h2>Allergy conflicts on today's menu</h2>
    <?php foreach ($conflicts as $c): ?>
      <div class="alert-conflict"><?= e($c['child']) ?> is allergic to <?= e(strtoupper($c['allergen'])) ?>. <?= e($c['meal']) ?> contains it.</div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Attendance today</h2>
  <?php if (!$attendance): ?>
    <p class="muted">No child has been checked in yet today.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>Child</th><th>Class</th><th>In</th><th>Dropped by</th><th>Out</th><th>Collected by</th></tr>
      <?php foreach ($attendance as $a): ?>
        <tr>
          <td><?= e($a['child_name']) ?></td>
          <td><?= e($a['class_name']) ?></td>
          <td><?= $a['check_in'] ? e(date('H:i', strtotime($a['check_in']))) : '-' ?></td>
          <td><?= e($a['dropped_by'] ?? '-') ?></td>
          <td><?= $a['check_out'] ? e(date('H:i', strtotime($a['check_out']))) : '<span class="badge badge-ok">At centre</span>' ?></td>
          <td><?= e($a['picked_by'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="row between">
    <h2>Latest activity</h2>
    <a class="btn btn-light btn-small" href="<?= e(url('admin/logs.php')) ?>">Full activity log</a>
  </div>
  <?php if (!$recent): ?>
    <p class="muted">Nothing recorded yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= e(date('H:i', strtotime($r['created_at']))) ?></td>
          <td><?= e($r['user_name']) ?> <span class="muted">(<?= e($r['role'] ?? 'guest') ?>)</span></td>
          <td><?= e($r['action']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="row">
  <a class="btn" href="<?= e(url('admin/staff.php')) ?>">Manage staff</a>
  <a class="btn btn-light" href="<?= e(url('admin/settings.php')) ?>">Fees and packages</a>
</div>

<?php page_footer(); ?>