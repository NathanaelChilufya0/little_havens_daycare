<?php
require_once __DIR__ . '/../auth.php';
$user = require_role('parent');

$today = date('Y-m-d');
$menu  = todays_menu();

$children = q(
    'SELECT c.*, p.name AS package_name, h.allergies, h.condition_notes
     FROM children c
     LEFT JOIN packages p ON p.id = c.package_id
     LEFT JOIN health_profiles h ON h.child_id = c.id
     WHERE c.parent_id = ? AND c.active = 1
     ORDER BY c.name',
    [$user['id']]
);

$owing = q1(
    "SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total
     FROM payments WHERE parent_id = ? AND status = 'pending'",
    [$user['id']]
);

page_header('My children');
?>

<?php if ($owing && (int) $owing['n'] > 0): ?>
  <div class="flash flash-warn">
    You have <?= (int) $owing['n'] ?> unpaid fee<?= (int) $owing['n'] === 1 ? '' : 's' ?>
    totalling <?= e(kwacha($owing['total'])) ?>.
    <a href="<?= e(url('parent/pay.php')) ?>">Pay now</a>
  </div>
<?php endif; ?>

<?php if (!$children): ?>
  <div class="card">
    <h2>No children registered yet</h2>
    <p>Register your child to see their day here.</p>
    <a class="btn" href="<?= e(url('parent/register_child.php')) ?>">Register a child</a>
  </div>
<?php endif; ?>

<?php foreach ($children as $c):
    $att       = attendance_for((int) $c['id'], $today);
    $meds      = q('SELECT * FROM medications WHERE child_id = ? AND active = 1 ORDER BY time_due', [$c['id']]);
    $conflicts = allergy_conflicts($c['allergies'], $menu);
    $timeline  = child_timeline((int) $c['id'], $today);
    $history   = q('SELECT * FROM attendance WHERE child_id = ? ORDER BY att_date DESC LIMIT 5', [$c['id']]);

    if (!$att || !$att['check_in']) {
        $status = ['Not at the centre yet', 'badge-muted'];
    } elseif ($att['check_out']) {
        $status = ['Collected', 'badge-info'];
    } else {
        $status = ['At the centre', 'badge-ok'];
    }
?>
<section class="card accent" aria-labelledby="child-<?= (int) $c['id'] ?>">
  <div class="row between">
    <div>
      <h2 id="child-<?= (int) $c['id'] ?>"><?= e($c['name']) ?></h2>
      <span class="muted">Age <?= (int) $c['age'] ?> &middot; <?= e($c['class_name']) ?> class
        &middot; <?= e($c['package_name'] ?? 'No package') ?></span>
    </div>
    <span class="badge <?= e($status[1]) ?>"><?= e($status[0]) ?></span>
  </div>

  <?php if (keywords($c['allergies'])): ?>
    <div class="alert-allergy">Allergy: <?= e(strtoupper($c['allergies'])) ?></div>
  <?php endif; ?>
  <?php foreach ($conflicts as $x): ?>
    <div class="alert-conflict">
      <?= e(first_name($c['name'])) ?> is allergic to <?= e(strtoupper($x['allergen'])) ?>.
      Today's menu has it in: <?= e($x['meal']) ?>. Staff can see this alert.
    </div>
  <?php endforeach; ?>

  <div class="grid" style="margin-top:14px">
    <div>
      <h3>Today so far</h3>
      <?php if ($timeline): ?>
        <ul class="timeline">
          <?php foreach ($timeline as $t): ?>
            <li class="t-<?= e($t['type']) ?>">
              <span class="time"><?= e(date('H:i', strtotime($t['time']))) ?></span><?= e($t['text']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted">Nothing recorded yet today. Updates appear here as staff log them.</p>
      <?php endif; ?>

      <div class="summary">
        <div class="label">AI-assisted care summary</div>
        <?= e(build_care_summary((int) $c['id'], $today)) ?>
      </div>
    </div>

    <div>
      <h3>Health and medicine</h3>
      <p class="muted" style="margin:0 0 8px">
        <?= $c['condition_notes'] ? e($c['condition_notes']) : 'No conditions recorded.' ?>
      </p>
      <?php if (!$meds): ?>
        <p class="muted">No medicine on record.</p>
      <?php endif; ?>
      <?php foreach ($meds as $m):
          $given = q1('SELECT given_at FROM medication_log WHERE medication_id = ? AND DATE(given_at) = ?', [$m['id'], $today]);
      ?>
        <div class="stack" style="margin-bottom:10px">
          <div>
            <strong><?= e($m['name']) ?></strong>, <?= e($m['dosage']) ?> at <?= e(date('H:i', strtotime($m['time_due']))) ?>
            <?php if ($given): ?>
              <span class="badge badge-ok">Given <?= e(date('H:i', strtotime($given['given_at']))) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($m['left_at_home']): ?>
            <div class="muted">
              Medicine left at home.
              <?php if ($m['centre_supplied'] && $m['nurse_status'] === 'confirmed'): ?>
                The centre is supplying it (extra fee <?= e(kwacha($m['fee'])) ?>).
              <?php elseif ($m['centre_supplied'] && $m['nurse_status'] === 'pending'): ?>
                Waiting for the nurse to confirm the centre can supply it.
              <?php elseif ($m['nurse_status'] === 'declined'): ?>
                <strong>The nurse could not supply it. Please bring the medicine.</strong>
              <?php else: ?>
                Please bring it to the centre.
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <h3 style="margin-top:16px">Recent attendance</h3>
  <?php if ($history): ?>
  <div class="table-wrap">
    <table>
      <tr><th>Date</th><th>In</th><th>Dropped by</th><th>Out</th><th>Collected by</th></tr>
      <?php foreach ($history as $h): ?>
        <tr>
          <td><?= e(date('D j M', strtotime($h['att_date']))) ?></td>
          <td><?= $h['check_in'] ? e(date('H:i', strtotime($h['check_in']))) : '-' ?></td>
          <td><?= e($h['dropped_by'] ?? '-') ?></td>
          <td><?= $h['check_out'] ? e(date('H:i', strtotime($h['check_out']))) : '-' ?></td>
          <td><?= e($h['picked_by'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
    <p class="muted">No attendance yet.</p>
  <?php endif; ?>

  <p class="row" style="margin-top:12px">
    <a class="btn btn-light btn-small" href="<?= e(url('parent/pickups.php?child=' . (int) $c['id'])) ?>">Pickup people</a>
    <a class="btn btn-light btn-small" href="<?= e(url('parent/pay.php')) ?>">Payments</a>
  </p>
</section>
<?php endforeach; ?>

<?php page_footer(); ?>
