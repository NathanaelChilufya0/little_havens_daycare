<?php
require_once __DIR__ . '/../extra.php';

$user = require_role('admin');

// Column names are looked up (or added) first, because ALTER TABLE cannot run inside a transaction.
$feeCol = col_or_add('packages', ['monthly_fee', 'fee', 'price', 'cost', 'amount'], 'DECIMAL(10,2) NOT NULL DEFAULT 0');
$pc     = payment_cols();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = posted('action');

    if ($action === 'save_package') {
        $id   = (int) ($_POST['id'] ?? 0);
        $name = posted('name');
        $fee  = (float) posted('fee');
        if ($name === '' || $fee < 0) {
            set_flash('Enter a package name and a fee of zero or more.', 'error');
        } else {
            update_row('packages', ['name' => $name, $feeCol => $fee], 'id = ?', [$id]);
            log_activity('Updated package: ' . $name);
            set_flash('Package saved.');
        }
    } elseif ($action === 'add_package') {
        $name = posted('name');
        $fee  = (float) posted('fee');
        if ($name === '' || $fee < 0) {
            set_flash('Enter a package name and a fee of zero or more.', 'error');
        } else {
            insert_row('packages', ['name' => $name, $feeCol => $fee, 'description' => posted('description')]);
            log_activity('Added package: ' . $name);
            set_flash('Package added.');
        }
        } elseif ($action === 'generate') {
        $month   = date('F Y');
        $desc    = 'Childcare fees: ' . $month;
        $parents = q(
            "SELECT c.parent_id, SUM(p.`$feeCol`) AS pkg
             FROM children c JOIN packages p ON p.id = c.package_id
             WHERE c.active = 1 GROUP BY c.parent_id"
        );
        $made = 0;
        foreach ($parents as $p) {
            // Skip parents who already have an invoice for this month (including ones from the demo data).
            $exists = q1("SELECT id FROM payments WHERE parent_id = ? AND `{$pc['desc']}` LIKE ?", [$p['parent_id'], '%' . $month . '%']);
            if ($exists) {
                continue;
            }
            $med = (float) scalar(
                "SELECT COALESCE(SUM(m.fee), 0) FROM medications m JOIN children c ON c.id = m.child_id
                 WHERE c.parent_id = ? AND m.active = 1 AND m.centre_supplied = 1 AND m.nurse_status = 'confirmed'",
                [$p['parent_id']]
            );
            $total = (float) $p['pkg'] + $med;
            if ($total <= 0) {
                continue;
            }
            insert_row('payments', [
                'parent_id'    => $p['parent_id'],
                'amount'       => $total,
                'status'       => 'pending',
                $pc['desc']    => $desc . ($med > 0 ? ' (includes centre medicine ' . kwacha($med) . ')' : ''),
                $pc['ref']     => 'LH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
                $pc['created'] => date('Y-m-d H:i:s'),
            ]);
            $made++;
        }
        log_activity("Generated $made invoice(s) for $month");
        set_flash($made > 0 ? "$made invoice(s) created. Parents can now pay." : 'No new invoices: every parent already has one for this month.', $made > 0 ? 'ok' : 'warn');
    }
    redirect('admin/settings.php');
}

$packages = q('SELECT * FROM packages ORDER BY id');
$recent   = q(
    'SELECT p.*, u.name AS parent_name FROM payments p JOIN users u ON u.id = p.parent_id ORDER BY p.id DESC LIMIT 10'
);

page_header('Fees and packages');
?>

<div class="card">
  <h2>Childcare packages</h2>
  <?php foreach ($packages as $p): ?>
    <form method="post" class="row" style="margin-bottom:10px">
      <input type="hidden" name="action" value="save_package">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <input type="text" name="name" value="<?= e($p['name']) ?>" style="max-width:260px" required>
      <span>K</span>
      <input type="number" name="fee" step="0.01" min="0" value="<?= e($p[$feeCol]) ?>" style="max-width:130px" required>
      <button class="btn btn-light btn-small" type="submit">Save</button>
    </form>
  <?php endforeach; ?>
  <?php if (!$packages): ?><p class="muted">No packages yet. Add one below.</p><?php endif; ?>

  <h3 style="margin-top:18px">Add a package</h3>
  <form method="post" class="row">
    <input type="hidden" name="action" value="add_package">
    <input type="text" name="name" placeholder="e.g. Full day" style="max-width:220px" required>
    <input type="text" name="description" placeholder="Short description" style="max-width:260px">
    <span>K</span>
    <input type="number" name="fee" step="0.01" min="0" placeholder="Monthly fee" style="max-width:130px" required>
    <button class="btn btn-small" type="submit">Add</button>
  </form>
</div>

<div class="card">
  <h2>Monthly invoices</h2>
  <p class="muted">Creates one unpaid invoice per parent for <?= e(date('F Y')) ?>: package fees for all their children, plus the extra fee for any centre-supplied medicine the nurse has confirmed (<?= e(kwacha(CENTRE_MEDICINE_FEE)) ?> each). Safe to press twice.</p>
  <form method="post">
    <input type="hidden" name="action" value="generate">
    <button class="btn" type="submit">Generate <?= e(date('F')) ?> invoices</button>
  </form>
</div>

<div class="card">
  <h2>Latest fees</h2>
  <?php if (!$recent): ?>
    <p class="muted">No fees yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>Parent</th><th>Description</th><th>Amount</th><th>Status</th></tr>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= e($r['parent_name']) ?></td>
          <td><?= e($r[$pc['desc']] ?? 'Daycare fee') ?></td>
          <td><?= e(kwacha($r['amount'])) ?></td>
          <td><?= $r['status'] === 'paid' ? '<span class="badge badge-ok">Paid</span>' : '<span class="badge badge-due">Unpaid</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php page_footer(); ?>