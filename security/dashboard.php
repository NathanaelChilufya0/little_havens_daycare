<?php
require_once __DIR__ . '/../auth.php';

$user = require_role('security');
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $childId = (int)$_POST['child_id'];
    $droppedBy = trim($_POST['dropped_by']);

    $existing = attendance_for($childId, $today);

    if (!$existing) {

        insert(
            "INSERT INTO attendance
             (child_id, att_date, check_in, dropped_by, checked_in_by)
             VALUES (?,?,?,?,?)",
            [
                $childId,
                $today,
                date('Y-m-d H:i:s'),
                $droppedBy,
                $user['name']
            ]
        );

        log_activity("Checked child in");
        set_flash("Child checked in.");
    }

    redirect('security/dashboard.php');
}

$children = q(
    "SELECT c.*
     FROM children c
     WHERE c.active = 1
     ORDER BY c.name"
);

page_header('Security Gate');
?>

<?php foreach ($children as $child):

$att = attendance_for($child['id'], $today);
?>

<div class="card">

<div class="row between">

<div>
<h2><?= e($child['name']) ?></h2>
<p><?= e($child['class_name']) ?></p>
</div>

<div>

<?php if (!$att): ?>

<span class="badge badge-muted">
Not Checked In
</span>

<?php elseif ($att['check_out']): ?>

<span class="badge badge-info">
Collected
</span>

<?php else: ?>

<span class="badge badge-ok">
At Centre
</span>

<?php endif; ?>

</div>
</div>

<?php if (!$att): ?>

<form method="post" class="form">

<input
    type="hidden"
    name="child_id"
    value="<?= (int)$child['id'] ?>"
>

<label>Dropped Off By</label>

<input
    type="text"
    name="dropped_by"
    required
>

<button class="btn btn-green">
Check In
</button>

</form>

<?php elseif (!$att['check_out']): ?>

<a class="btn"
   href="<?= e(url(
      'security/pickup.php?child=' . $child['id']
   )) ?>">
   Check Out
</a>

<?php endif; ?>

</div>

<?php endforeach; ?>

<?php page_footer(); ?>