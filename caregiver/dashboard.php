<?php
require_once __DIR__ . '/../auth.php';

$user = require_role('caregiver');
$today = date('Y-m-d');
$menu = todays_menu();

$children = q(
    "SELECT c.*,
            h.allergies,
            h.nurse_notes,
            a.check_in,
            a.check_out
     FROM children c
     JOIN attendance a ON a.child_id = c.id
     LEFT JOIN health_profiles h ON h.child_id = c.id
     WHERE a.att_date = ?
       AND a.check_in IS NOT NULL
       AND a.check_out IS NULL
     ORDER BY c.name",
    [$today]
);

page_header("Today's Children");
?>

<?php if (!$children): ?>
<div class="card">
    <h2>No children checked in</h2>
    <p>Children will appear here after security checks them in.</p>
</div>
<?php endif; ?>

<?php foreach ($children as $child): ?>

<section class="card accent">

    <div class="row between">
        <h2><?= e($child['name']) ?></h2>
        <a class="btn btn-small"
           href="<?= e(url('caregiver/log.php?child=' . $child['id'])) ?>">
            Log Care
        </a>
    </div>

    <?php if (!empty($child['allergies'])): ?>
        <div class="alert-allergy">
            ALLERGY ALERT:
            <?= e(strtoupper($child['allergies'])) ?>
        </div>
    <?php endif; ?>

    <?php
    $conflicts = allergy_conflicts($child['allergies'], $menu);
    foreach ($conflicts as $conflict):
    ?>
        <div class="alert-conflict">
            Menu conflict:
            <?= e($conflict['meal']) ?>
            contains
            <?= e($conflict['allergen']) ?>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($child['nurse_notes'])): ?>
        <div class="card">
            <strong>Nurse Notes</strong>
            <p><?= e($child['nurse_notes']) ?></p>
        </div>
    <?php endif; ?>

    <div class="row">
        <span class="badge badge-ok">
            Checked In
        </span>

        <a class="btn btn-light btn-small"
           href="<?= e(url('caregiver/log.php?child=' . $child['id'])) ?>">
            View Timeline
        </a>
    </div>

</section>

<?php endforeach; ?>

<?php page_footer(); ?>