<?php
require_once __DIR__ . '/../extra.php';

$user = require_role('admin');

$role  = $_GET['role'] ?? '';
$roles = ['parent', 'nurse', 'caregiver', 'security', 'admin'];

if (in_array($role, $roles, true)) {
    $rows = q('SELECT * FROM activity_log WHERE role = ? ORDER BY id DESC LIMIT 200', [$role]);
} else {
    $role = '';
    $rows = q('SELECT * FROM activity_log ORDER BY id DESC LIMIT 200');
}

page_header('Activity log');
?>

<div class="card">
  <div class="row between">
    <p class="muted" style="margin:0">Every login, check-in, medicine dose, release and blocked pickup is recorded here (latest 200).</p>
    <form method="get" class="row">
      <select name="role" onchange="this.form.submit()" style="width:auto">
        <option value="">All roles</option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= e($r) ?>"<?= $role === $r ? ' selected' : '' ?>><?= e(ucfirst($r)) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-light btn-small">Filter</button></noscript>
    </form>
  </div>

  <div class="table-wrap" style="margin-top:12px">
    <table>
      <tr><th>When</th><th>Who</th><th>Role</th><th>What</th></tr>
      <?php foreach ($rows as $r):
          $blocked = stripos($r['action'], 'Blocked') === 0; ?>
        <tr>
          <td><?= e(date('D j M, H:i', strtotime($r['created_at']))) ?></td>
          <td><?= e($r['user_name']) ?></td>
          <td><?= e($r['role'] ?? '-') ?></td>
          <td><?= $blocked ? '<span class="badge badge-overdue">' . e($r['action']) . '</span>' : e($r['action']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="muted">Nothing recorded yet.</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<?php page_footer(); ?><?php
require_once __DIR__ . '/../extra.php';

$user = require_role('admin');

$role  = $_GET['role'] ?? '';
$roles = ['parent', 'nurse', 'caregiver', 'security', 'admin'];

if (in_array($role, $roles, true)) {
    $rows = q('SELECT * FROM activity_log WHERE role = ? ORDER BY id DESC LIMIT 200', [$role]);
} else {
    $role = '';
    $rows = q('SELECT * FROM activity_log ORDER BY id DESC LIMIT 200');
}

page_header('Activity log');
?>

<div class="card">
  <div class="row between">
    <p class="muted" style="margin:0">Every login, check-in, medicine dose, release and blocked pickup is recorded here (latest 200).</p>
    <form method="get" class="row">
      <select name="role" onchange="this.form.submit()" style="width:auto">
        <option value="">All roles</option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= e($r) ?>"<?= $role === $r ? ' selected' : '' ?>><?= e(ucfirst($r)) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-light btn-small">Filter</button></noscript>
    </form>
  </div>

  <div class="table-wrap" style="margin-top:12px">
    <table>
      <tr><th>When</th><th>Who</th><th>Role</th><th>What</th></tr>
      <?php foreach ($rows as $r):
          $blocked = stripos($r['action'], 'Blocked') === 0; ?>
        <tr>
          <td><?= e(date('D j M, H:i', strtotime($r['created_at']))) ?></td>
          <td><?= e($r['user_name']) ?></td>
          <td><?= e($r['role'] ?? '-') ?></td>
          <td><?= $blocked ? '<span class="badge badge-overdue">' . e($r['action']) . '</span>' : e($r['action']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="muted">Nothing recorded yet.</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<?php page_footer(); ?>