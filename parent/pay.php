<?php
require_once __DIR__ . '/../extra.php';

$user = require_role('parent');
$pc   = payment_cols();   // makes sure description / method / reference / paid_at columns exist

$methods = ['Airtel Money', 'MTN MoMo', 'Zamtel Kwacha'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = posted('method');
    $phone  = preg_replace('/\D+/', '', posted('phone'));
    $due    = q("SELECT * FROM payments WHERE parent_id = ? AND status = 'pending'", [$user['id']]);
    $total  = array_sum(array_column($due, 'amount'));

    if (!$due) {
        set_flash('You have no unpaid fees.', 'warn');
    } elseif (!in_array($method, $methods, true)) {
        set_flash('Choose a mobile money provider.', 'error');
    } elseif (strlen($phone) < 9) {
        set_flash('Enter the mobile money number you are paying from.', 'error');
    } else {
        // SIMULATION: no money moves. A real build would call the Airtel / MTN API here.
        $ref = 'LH' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        update_row('payments', [
            'status'      => 'paid',
            $pc['method'] => $method . ' (' . $phone . ')',
            $pc['ref']    => $ref,
            $pc['paid_at'] => date('Y-m-d H:i:s'),
        ], "parent_id = ? AND status = 'pending'", [$user['id']]);
        log_activity('Simulated payment of ' . kwacha($total) . ', ref ' . $ref);
        set_flash('Payment of ' . kwacha($total) . ' received (simulated). Reference: ' . $ref);
    }
    redirect('parent/pay.php');
}

$pending = q("SELECT * FROM payments WHERE parent_id = ? AND status = 'pending' ORDER BY id", [$user['id']]);
$total   = array_sum(array_column($pending, 'amount'));
$history = q("SELECT * FROM payments WHERE parent_id = ? AND status = 'paid' ORDER BY id DESC LIMIT 15", [$user['id']]);

page_header('Payments');
?>

<div class="flash flash-warn">Demonstration only: this screen simulates mobile money. No real money moves.</div>

<div class="card accent">
  <h2>Fees to pay</h2>
  <?php if (!$pending): ?>
    <p>You have no unpaid fees. New fees appear here when the centre issues them.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>Description</th><th>Amount</th></tr>
      <?php foreach ($pending as $p): ?>
        <tr><td><?= e($p[$pc['desc']] ?? 'Daycare fee') ?></td><td><?= e(kwacha($p['amount'])) ?></td></tr>
      <?php endforeach; ?>
      <tr><td><strong>Total due</strong></td><td><strong><?= e(kwacha($total)) ?></strong></td></tr>
    </table>
  </div>

  <form method="post" class="form" autocomplete="off">
    <div class="form-grid">
      <div>
        <label for="method">Pay with</label>
        <select id="method" name="method" required>
          <?php foreach ($methods as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="phone">Mobile money number</label>
        <input type="tel" id="phone" name="phone" placeholder="0971234567" required>
      </div>
    </div>
    <p><button class="btn btn-green" type="submit">Pay <?= e(kwacha($total)) ?></button></p>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Payment history</h2>
  <?php if (!$history): ?>
    <p class="muted">No payments yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>Date</th><th>Description</th><th>Amount</th><th>Method</th><th>Reference</th></tr>
      <?php foreach ($history as $h): ?>
        <tr>
          <td><?= !empty($h[$pc['paid_at']]) ? e(date('D j M Y, H:i', strtotime($h[$pc['paid_at']]))) : '-' ?></td>
          <td><?= e($h[$pc['desc']] ?? 'Daycare fee') ?></td>
          <td><?= e(kwacha($h['amount'])) ?></td>
          <td><?= e($h[$pc['method']] ?? '-') ?></td>
          <td><?= e($h[$pc['ref']] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php page_footer(); ?>