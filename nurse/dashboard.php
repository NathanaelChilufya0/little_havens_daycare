<?php
require_once __DIR__ . '/../extra.php';

$user  = require_role('nurse');
$today = date('Y-m-d');

/** "10:05" or "10:05:00" -> minutes since midnight. */
function clock_mins(string $t): int
{
    $p = explode(':', $t);
    return ((int) $p[0]) * 60 + (int) ($p[1] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['sim_time'])) {
        $t = trim($_POST['sim_time']);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) {
            $_SESSION['sim_time'] = $t;
            set_flash("Simulated time set to $t.");
        } else {
            set_flash('Enter a time like 10:00.', 'error');
        }

    } elseif (isset($_POST['sim_reset'])) {
        unset($_SESSION['sim_time']);
        set_flash('Using the real clock again.');

    } elseif (isset($_POST['give_med'])) {
        $medId = (int) ($_POST['med_id'] ?? 0);
        $med   = q1(
            'SELECT m.*, c.name AS child_name FROM medications m JOIN children c ON c.id = m.child_id
             WHERE m.id = ? AND m.active = 1',
            [$medId]
        );
        $att = $med ? attendance_for((int) $med['child_id'], $today) : null;

        if (!$med) {
            set_flash('That medicine could not be found.', 'error');
        } elseif ((int) $med['left_at_home'] === 1 && $med['nurse_status'] !== 'confirmed') {
            set_flash('The medicine is not at the centre. Ask the parent to bring it.', 'error');
        } elseif (!$att || !$att['check_in'] || $att['check_out']) {
            set_flash($med['child_name'] . ' is not at the centre right now. Security must check the child in first.', 'error');
        } elseif (q1('SELECT id FROM medication_log WHERE medication_id = ? AND DATE(given_at) = ?', [$medId, $today])) {
            set_flash('That dose was already given today.', 'warn');
        } else {
            insert_row('medication_log', [
                'medication_id' => $medId,
                'child_id'      => $med['child_id'],
                'nurse_id'      => $user['id'],
                'nurse_name'    => $user['name'],
                'given_at'      => date('Y-m-d H:i:s'),
            ]);
            log_activity('Medicine given: ' . $med['name'] . ' to ' . $med['child_name']);
            set_flash($med['name'] . ' (' . $med['dosage'] . ') recorded as given to ' . $med['child_name'] . '.');
        }

    } elseif (isset($_POST['approve']) || isset($_POST['decline'])) {
        $id  = (int) ($_POST['approve'] ?? $_POST['decline']);
        $med = q1(
            "SELECT m.*, c.name AS child_name FROM medications m JOIN children c ON c.id = m.child_id
             WHERE m.id = ? AND m.nurse_status = 'pending'",
            [$id]
        );
        if ($med && isset($_POST['approve'])) {
            run("UPDATE medications SET nurse_status = 'confirmed', fee = CASE WHEN fee = 0 THEN ? ELSE fee END WHERE id = ?",
                [CENTRE_MEDICINE_FEE, $id]);
            log_activity('Confirmed centre-supplied medicine for ' . $med['child_name']);
            set_flash('Confirmed: the centre will supply ' . $med['name'] . ' for ' . $med['child_name'] . ' (extra fee added).');
        } elseif ($med) {
            run("UPDATE medications SET nurse_status = 'declined' WHERE id = ?", [$id]);
            log_activity('Declined centre-supplied medicine for ' . $med['child_name']);
            set_flash('Declined. The parent will be asked to bring the medicine.', 'warn');
        }

    } elseif (isset($_POST['save_notes'])) {
        $childId = (int) ($_POST['child_id'] ?? 0);
        $notes   = mb_substr(trim($_POST['nurse_notes'] ?? ''), 0, 500);
        if (q1('SELECT id FROM children WHERE id = ?', [$childId])) {
            if (q1('SELECT id FROM health_profiles WHERE child_id = ?', [$childId])) {
                run('UPDATE health_profiles SET nurse_notes = ? WHERE child_id = ?', [$notes === '' ? null : $notes, $childId]);
            } else {
                insert_row('health_profiles', ['child_id' => $childId, 'nurse_notes' => $notes]);
            }
            log_activity('Updated nurse notes for child #' . $childId);
            set_flash('Nurse notes saved. Caregivers can see them.');
        }
    }
    redirect('nurse/dashboard.php');
}

$now = sim_time();

// Medicine that is physically at the centre: parent-supplied, or centre-supplied and confirmed.
$meds = q(
    "SELECT m.*, c.name AS child_name, c.class_name, h.allergies
     FROM medications m
     JOIN children c ON c.id = m.child_id
     LEFT JOIN health_profiles h ON h.child_id = c.id
     WHERE m.active = 1 AND c.active = 1 AND (m.left_at_home = 0 OR m.nurse_status = 'confirmed')
     ORDER BY m.time_due, c.name"
);

$queue = [];
$given = [];
$dueCount = 0;
$overdueCount = 0;
foreach ($meds as $m) {
    $log = q1('SELECT given_at, nurse_name FROM medication_log WHERE medication_id = ? AND DATE(given_at) = ?', [$m['id'], $today]);
    if ($log) {
        $m['log'] = $log;
        $given[]  = $m;
        continue;
    }
    $att = attendance_for((int) $m['child_id'], $today);
    $m['present'] = $att && $att['check_in'] && !$att['check_out'];
    $late = clock_mins($now) - clock_mins($m['time_due']);
    if ($late < 0) {
        $m['state'] = ['Due at ' . substr($m['time_due'], 0, 5), 'badge-muted', ''];
    } elseif ($late <= 30) {
        $m['state'] = ['DUE NOW', 'badge-due', 'var(--amber-soft)'];
        $dueCount++;
    } else {
        $m['state'] = ['OVERDUE by ' . $late . ' min', 'badge-overdue', 'var(--red-soft)'];
        $overdueCount++;
    }
    $queue[] = $m;
}

$requests = q(
    "SELECT m.*, c.name AS child_name FROM medications m JOIN children c ON c.id = m.child_id
     WHERE m.active = 1 AND m.nurse_status = 'pending' ORDER BY m.id"
);

// Parent left it at home, and the centre is not supplying it: someone must bring it.
$missing = q(
    "SELECT m.*, c.name AS child_name FROM medications m JOIN children c ON c.id = m.child_id
     WHERE m.active = 1 AND m.left_at_home = 1 AND m.nurse_status IN ('not_needed', 'declined') ORDER BY c.name"
);

$present = q(
    "SELECT c.id, c.name, c.class_name, h.allergies, h.condition_notes, h.nurse_notes
     FROM children c
     JOIN attendance a ON a.child_id = c.id AND a.att_date = ? AND a.check_in IS NOT NULL AND a.check_out IS NULL
     LEFT JOIN health_profiles h ON h.child_id = c.id
     WHERE c.active = 1 ORDER BY c.name",
    [$today]
);

page_header('Nurse Health Desk');
?>

<div class="stats">
  <div class="stat"><div class="num"><?= (int) $overdueCount ?></div><div class="lbl">Overdue doses</div></div>
  <div class="stat"><div class="num"><?= (int) $dueCount ?></div><div class="lbl">Due now</div></div>
  <div class="stat"><div class="num"><?= count($given) ?></div><div class="lbl">Given today</div></div>
  <div class="stat"><div class="num"><?= count($requests) ?></div><div class="lbl">Supply requests</div></div>
</div>

<div class="card">
  <div class="row between">
    <div>
      <h2 style="margin:0">Clock: <?= e($now) ?> <?= isset($_SESSION['sim_time']) ? '<span class="badge badge-info">simulated</span>' : '<span class="badge badge-muted">real time</span>' ?></h2>
      <span class="muted">Demo tool: jump the clock to make a dose due without waiting.</span>
    </div>
    <form method="post" class="row">
      <input type="time" name="sim_time" value="<?= e($now) ?>" style="width:auto" required>
      <button class="btn btn-small" type="submit">Set time</button>
      <?php if (isset($_SESSION['sim_time'])): ?>
        <button class="btn btn-light btn-small" name="sim_reset" value="1" type="submit">Use real time</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <h2>Medicine queue</h2>
  <p class="muted">Give exactly what the parent wrote: the dosage and instructions below. The system never suggests a dose.</p>
  <?php if (!$queue): ?>
    <p class="muted">No doses waiting. Every dose at the centre has been given.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>Status</th><th>Child</th><th>Medicine and dosage</th><th>Parent's instructions</th><th></th></tr>
      <?php foreach ($queue as $m): ?>
        <tr<?= $m['state'][2] ? ' style="background:' . $m['state'][2] . '"' : '' ?>>
          <td><span class="badge <?= e($m['state'][1]) ?>"><?= e($m['state'][0]) ?></span></td>
          <td>
            <strong><?= e($m['child_name']) ?></strong>
            <div class="muted"><?= e($m['class_name']) ?></div>
            <?php if (!is_none($m['allergies'] ?? '')): ?><span class="badge badge-overdue">Allergy: <?= e($m['allergies']) ?></span><?php endif; ?>
          </td>
          <td>
            <?= e($m['name']) ?>, <strong><?= e($m['dosage']) ?></strong> at <?= e(substr($m['time_due'], 0, 5)) ?>
            <?php if ($m['left_at_home']): ?><div class="muted">Centre-supplied (extra fee <?= e(kwacha($m['fee'])) ?>)</div><?php endif; ?>
          </td>
          <td><?= $m['instructions'] ? e($m['instructions']) : '<span class="muted">None written</span>' ?></td>
          <td>
            <form method="post">
              <input type="hidden" name="med_id" value="<?= (int) $m['id'] ?>">
              <?php if ($m['present']): ?>
                <button class="btn btn-green btn-small" name="give_med" value="1" type="submit">Mark given</button>
              <?php else: ?>
                <button class="btn btn-small" type="button" disabled title="Security must check the child in first">Not at centre</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($requests): ?>
<div class="card accent">
  <h2>Centre-supplied medicine requests</h2>
  <p class="muted">The parent left this medicine at home and asked the centre to supply it for an extra fee. Confirm only if you have it.</p>
  <?php foreach ($requests as $r): ?>
    <div class="row between" style="margin-bottom:10px">
      <div>
        <strong><?= e($r['child_name']) ?></strong>: <?= e($r['name']) ?>, <?= e($r['dosage']) ?> at <?= e(substr($r['time_due'], 0, 5)) ?>
        <span class="muted">(fee <?= e(kwacha($r['fee'] > 0 ? $r['fee'] : CENTRE_MEDICINE_FEE)) ?>)</span>
        <?php if ($r['instructions']): ?><div class="muted">Parent says: <?= e($r['instructions']) ?></div><?php endif; ?>
      </div>
      <form method="post" class="row">
        <button class="btn btn-green btn-small" name="approve" value="<?= (int) $r['id'] ?>" type="submit">Confirm</button>
        <button class="btn btn-danger btn-small" name="decline" value="<?= (int) $r['id'] ?>" type="submit">Decline</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($missing): ?>
<div class="card">
  <h2>Medicine not at the centre</h2>
  <?php foreach ($missing as $m): ?>
    <p><strong><?= e($m['child_name']) ?></strong>: <?= e($m['name']) ?> was left at home
      <?= $m['nurse_status'] === 'declined' ? 'and the centre cannot supply it.' : '.' ?> Ask the parent to bring it.</p>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($given): ?>
<div class="card">
  <h2>Given today</h2>
  <div class="table-wrap">
    <table>
      <tr><th>Child</th><th>Medicine</th><th>Given at</th><th>By</th></tr>
      <?php foreach ($given as $m): ?>
        <tr>
          <td><?= e($m['child_name']) ?></td>
          <td><?= e($m['name']) ?>, <?= e($m['dosage']) ?></td>
          <td><?= e(date('H:i', strtotime($m['log']['given_at']))) ?></td>
          <td><?= e($m['log']['nurse_name']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h2>Children at the centre: health notes</h2>
  <?php if (!$present): ?>
    <p class="muted">No children are checked in right now.</p>
  <?php endif; ?>
  <?php foreach ($present as $c): ?>
    <div style="border-top:1px solid var(--lilac);padding:12px 0">
      <strong><?= e($c['name']) ?></strong> <span class="muted">(<?= e($c['class_name']) ?>)</span>
      <?php if (!is_none($c['allergies'] ?? '')): ?><span class="badge badge-overdue">Allergy: <?= e($c['allergies']) ?></span><?php endif; ?>
      <?php if (!is_none($c['condition_notes'] ?? '')): ?><div class="muted">Parent says: <?= e($c['condition_notes']) ?></div><?php endif; ?>
      <form method="post" class="row" style="margin-top:6px">
        <input type="hidden" name="child_id" value="<?= (int) $c['id'] ?>">
        <input type="text" name="nurse_notes" maxlength="500" value="<?= e($c['nurse_notes'] ?? '') ?>" placeholder="Nurse notes for the caregivers" style="max-width:520px">
        <button class="btn btn-light btn-small" name="save_notes" value="1" type="submit">Save</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php page_footer(); ?>