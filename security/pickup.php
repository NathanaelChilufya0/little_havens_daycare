<?php
require_once __DIR__ . '/../auth.php';

$user = require_role('security');

$childId = (int)($_GET['child'] ?? 0);

$child = q1(
    "SELECT * FROM children WHERE id=?",
    [$childId]
);

if (!$child) {
    redirect('security/dashboard.php');
}

$pickupPeople = q(
    "SELECT * FROM authorised_pickups
     WHERE child_id=?",
    [$childId]
);

$matched = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $idNumber = trim($_POST['id_number']);

    $matched = q1(
        "SELECT *
         FROM authorised_pickups
         WHERE child_id=?
         AND LOWER(TRIM(id_number))
             = LOWER(TRIM(?))",
        [$childId, $idNumber]
    );

    if (isset($_POST['release']) && $matched) {

        run(
            "UPDATE attendance
             SET check_out=?,
                 picked_by=?,
                 pickup_id=?,
                 checked_out_by=?
             WHERE child_id=?
             AND att_date=?",
            [
                date('Y-m-d H:i:s'),
                $matched['name'],
                $matched['id'],
                $user['name'],
                $childId,
                date('Y-m-d')
            ]
        );

        log_activity(
            "Released " . $child['name']
        );

        set_flash("Child released.");
        redirect('security/dashboard.php');
    }

    if (!$matched) {
        log_activity(
            "Blocked pickup attempt for "
            . $child['name']
        );
    }
}

page_header('Pickup Verification');
?>

<div class="card">

<h2><?= e($child['name']) ?></h2>

<h3>Authorised Pickups</h3>

<ul>

<?php foreach ($pickupPeople as $p): ?>

<li>

<?= e($p['name']) ?>

(
<?= e($p['relationship']) ?>
)

-

<?= e($p['id_number']) ?>

</li>

<?php endforeach; ?>

</ul>

</div>

<div class="card">

<form method="post">

<label>Collector Name</label>

<input type="text" name="collector">

<label>ID Number</label>

<input
    type="text"
    name="id_number"
    required
>

<button class="btn">
Verify
</button>

<?php if ($matched): ?>

<div class="alert-allowed">

AUTHORISED

<br>

<?= e($matched['name']) ?>

</div>

<button
    class="btn btn-green"
    name="release"
    value="1"
>

Release Child

</button>

<?php elseif ($_SERVER['REQUEST_METHOD']==='POST'): ?>

<div class="alert-block">

NOT AUTHORISED

</div>

<?php endif; ?>

</form>

</div>

<?php page_footer(); ?>