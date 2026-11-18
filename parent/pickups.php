<?php
require_once __DIR__ . '/../extra.php';

$user = require_role('parent');
col_or_add('authorised_pickups', ['phone'], 'VARCHAR(30) NULL');   // make sure phone numbers can be saved

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = posted('action');
    $childId = (int) ($_POST['child_id'] ?? 0);

    // The child must belong to the logged-in parent.
    $child = q1('SELECT id, name FROM children WHERE id = ? AND parent_id = ? AND active = 1', [$childId, $user['id']]);

    if ($action === 'add' && $child) {
        $name = posted('name');
        $idNo = posted('id_number');
        if ($name === '' || posted('relationship') === '' || $idNo === '') {
            set_flash('Enter the name, relationship and ID number of the person.', 'error');
        } elseif (q1('SELECT id FROM authorised_pickups WHERE child_id = ? AND LOWER(TRIM(id_number)) = LOWER(TRIM(?))', [$childId, $idNo])) {
            set_flash('That ID number is already on the list for ' . $child['name'] . '.', 'warn');
        } else {
            insert_row('authorised_pickups', [
                'child_id'     => $childId,
                'name'         => $name,
                'relationship' => posted('relationship'),
                'phone'        => posted('phone'),
                'id_number'    => $idNo,
            ]);
            log_activity('Added authorised pickup ' . $name . ' for ' . $child['name']);
            set_flash($name . ' can now collect ' . first_name($child['name']) . '. The guard will check their ID.');
        }
    } elseif ($action === 'remove') {
        $pid = (int) ($_POST['pickup_id'] ?? 0);
        $n = run(
            'DELETE FROM authorised_pickups WHERE id = ? AND child_id IN (SELECT id FROM children WHERE parent_id = ?)',
            [$pid, $user['id']]
        );
        if ($n) {
            log_activity('Removed an authorised pickup person (#' . $pid . ')');
            set_flash('Person removed. They can no longer collect the child.');
        }
    }
    redirect('parent/pickups.php');
}

$children = q('SELECT * FROM children WHERE parent_id = ? AND active = 1 ORDER BY name', [$user['id']]);

page_header('Pickup people');
?>

<p class="muted">Only the people listed here can collect your child. At the gate the guard asks for their ID and compares the ID number. Anyone else is blocked.</p>

<?php if (!$children): ?>
  <div class="card">
    <h2>No children registered yet</h2>
    <p>Register your child first, then add the people who may collect them.</p>
    <a class="btn" href="<?= e(url('parent/register_child.php')) ?>">Register a child</a>
  </div>
<?php endif; ?>

<?php foreach ($children as $c):
    $people = q('SELECT * FROM authorised_pickups WHERE child_id = ? ORDER BY id', [$c['id']]);
?>
<section class="card accent">
  <h2><?= e($c['name']) ?></h2>

  <?php if (!$people): ?>
    <div class="flash flash-warn">No one is authorised yet, so nobody can collect <?= e(first_name($c['name'])) ?>. Add at least one person.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>Name</th><th>Relationship</th><th>Phone</th><th>ID number</th><th></th></tr>
      <?php foreach ($people as $p): ?>
        <tr>
          <td><?= e($p['name']) ?></td>
          <td><?= e($p['relationship']) ?></td>
          <td><?= e($p['phone'] ?? '-') ?></td>
          <td><?= e($p['id_number']) ?></td>
          <td>
            <form method="post" class="inline-form" onsubmit="return confirm('Remove <?= e(addslashes($p['name'])) ?> from the list?')">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="pickup_id" value="<?= (int) $p['id'] ?>">
              <button class="btn btn-danger btn-small" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <h3 style="margin-top:16px">Add a person who may collect <?= e(first_name($c['name'])) ?></h3>
  <form method="post" class="form">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="child_id" value="<?= (int) $c['id'] ?>">
    <div class="form-grid">
      <div><label>Full name</label><input type="text" name="name" required></div>
      <div><label>Relationship <span class="hint">(e.g. Uncle, Neighbour)</span></label><input type="text" name="relationship" required></div>
      <div><label>Phone</label><input type="tel" name="phone"></div>
      <div><label>NRC / ID number</label><input type="text" name="id_number" required></div>
    </div>
    <p><button class="btn" type="submit">Add person</button></p>
  </form>
</section>
<?php endforeach; ?>

<?php page_footer(); ?>