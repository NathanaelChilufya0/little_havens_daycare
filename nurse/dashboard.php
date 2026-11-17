<?php
require_once __DIR__ . '/../auth.php';

$user = require_role('nurse');

if (isset($_POST['sim_time'])) {

    $_SESSION['sim_time'] =
        trim($_POST['sim_time']);

    set_flash(
        'Simulation time updated.'
    );

    redirect('nurse/dashboard.php');
}

if (isset($_POST['give_med'])) {

    $medId = (int)$_POST['med_id'];

    $med = q1(
        "SELECT * FROM medications WHERE id=?",
        [$medId]
    );

    if ($med) {

        insert(
            "INSERT INTO medication_log
             (
                medication_id,
                child_id,
                nurse_id,
                nurse_name,
                given_at
             )
             VALUES (?,?,?,?,?)",
            [
                $medId,
                $med['child_id'],
                $user['id'],
                $user['name'],
                date('Y-m-d H:i:s')
            ]
        );

        log_activity(
            'Medication administered'
        );
    }

    redirect('nurse/dashboard.php');
}

if (isset($_POST['approve'])) {

    run(
        "UPDATE medications
         SET nurse_status='confirmed'
         WHERE id=?",
        [(int)$_POST['approve']]
    );

    redirect('nurse/dashboard.php');
}

if (isset($_POST['decline'])) {

    run(
        "UPDATE medications
         SET nurse_status='declined'
         WHERE id=?",
        [(int)$_POST['decline']]
    );

    redirect('nurse/dashboard.php');
}

$dueMeds = q(
"
SELECT
m.*,
c.name AS child_name
FROM medications m
JOIN children c
 ON c.id = m.child_id
WHERE m.active = 1
ORDER BY m.time_due
"
);

$pendingSupply = q(
"
SELECT m.*, c.name child_name
FROM medications m
JOIN children c
ON c.id = m.child_id
WHERE m.nurse_status='pending'
"
);

page_header('Nurse Health Desk');
?>

<div class="card">

<h2>Simulated Time</h2>

<form method="post">

<input
    type="time"
    name="sim_time"
    value="<?= e(sim_time()) ?>"
>

<button class="btn">
Update
</button>

</form>

</div>

<div class="card">

<h2>Medicine Due</h2>

<?php foreach ($dueMeds as $med): ?>

<div class="row between">

<div>

<strong>
<?= e($med['child_name']) ?>
</strong>

<br>

<?= e($med['name']) ?>

-

<?= e($med['dosage']) ?>

-

<?= e($med['time_due']) ?>

</div>

<form method="post">

<input
type="hidden"
name="med_id"
value="<?= $med['id'] ?>"
>

<button
class="btn btn-green"
name="give_med"
value="1"
>

Mark Given

</button>

</form>

</div>

<?php endforeach; ?>

</div>

<div class="card">

<h2>Centre Supplied Medicine Requests</h2>

<?php foreach ($pendingSupply as $item): ?>

<div class="row between">

<div>

<?= e($item['child_name']) ?>

-

<?= e($item['name']) ?>

</div>

<form method="post">

<button
class="btn btn-green"
name="approve"
value="<?= $item['id'] ?>"
>

Confirm

</button>

<button
class="btn btn-danger"
name="decline"
value="<?= $item['id'] ?>"
>

Decline

</button>

</form>

</div>

<?php endforeach; ?>

</div>

<?php page_footer(); ?>