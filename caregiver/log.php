<?php
require_once __DIR__ . '/../auth.php';

$user = require_role('caregiver');

$childId = (int)($_GET['child'] ?? 0);

$child = q1(
    "SELECT * FROM children WHERE id=?",
    [$childId]
);

if (!$child) {
    redirect('caregiver/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    insert(
        "INSERT INTO care_log
        (child_id, log_type, details, staff_id, staff_name, logged_at)
        VALUES (?,?,?,?,?,?)",
        [
            $childId,
            $_POST['log_type'],
            trim($_POST['details']),
            $user['id'],
            $user['name'],
            date('Y-m-d H:i:s')
        ]
    );

    log_activity(
        'Care log added for ' . $child['name']
    );

    set_flash('Care activity recorded.');
    redirect('caregiver/log.php?child=' . $childId);
}

$timeline = child_timeline(
    $childId,
    date('Y-m-d')
);

page_header('Care Log');
?>

<section class="card">

<h2><?= e($child['name']) ?></h2>

<form method="post" class="form">

    <label>Activity Type</label>

    <select name="log_type" required>
        <option value="meal">Meal</option>
        <option value="nap">Nap</option>
        <option value="activity">Activity</option>
        <option value="cleaning">Cleaning</option>
    </select>

    <label>Details</label>

    <textarea
        name="details"
        rows="4"
        required
    ></textarea>

    <button class="btn">
        Save Entry
    </button>

</form>

</section>

<section class="card">

<h3>Today's Timeline</h3>

<?php if (!$timeline): ?>

<p>No entries yet today.</p>

<?php else: ?>

<ul class="timeline">

<?php foreach ($timeline as $item): ?>

<li>

<strong>
<?= e(date('H:i', strtotime($item['time']))) ?>
</strong>

<?= e($item['text']) ?>

</li>

<?php endforeach; ?>

</ul>

<?php endif; ?>

</section>

<?php page_footer(); ?>