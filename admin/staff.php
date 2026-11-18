<?php
require_once __DIR__ . '/../extra.php';

$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = posted('action');

    if ($action === 'add') {
        $name  = posted('name');
        $email = strtolower(posted('email'));
        $role  = posted('role');
        $pass  = posted('password') !== '' ? posted('password') : 'password';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['nurse', 'caregiver', 'security', 'admin'], true)) {
            set_flash('Enter a name, a valid email address and a role.', 'error');
        } elseif (strlen($pass) < 6) {
            set_flash('The password must be at least 6 characters.', 'error');
        } elseif (q1('SELECT id FROM users WHERE email = ?', [$email])) {
            set_flash('That email address already has an account.', 'error');
        } else {
            insert_row('users', [
                'name'          => $name,
                'email'         => $email,
                'phone'         => posted('phone'),
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'role'          => $role,
                'active'        => 1,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            log_activity("Created $role account for $name");
            set_flash("Account created for $name.");
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $user['id']) {
            set_flash('You cannot deactivate your own account.', 'warn');
        } else {
            run("UPDATE users SET active = 1 - active WHERE id = ? AND role <> 'parent'", [$id]);
            log_activity('Changed active status of user #' . $id);
            set_flash('Account status changed.');
        }
    } elseif ($action === 'reset') {
        $id = (int) ($_POST['id'] ?? 0);
        run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash('password', PASSWORD_DEFAULT), $id]);
        log_activity('Reset password for user #' . $id);
        set_flash('Password reset to: password');
    }
    redirect('admin/staff.php');
}

$staff = q("SELECT * FROM users WHERE role <> 'parent' ORDER BY role, name");
$parentCount = scalar("SELECT COUNT(*) FROM users WHERE role = 'parent'");

page_header('Staff and accounts');
?>

<div class="card">
  <h2>Staff accounts</h2>
  <div class="table-wrap">
    <table>
      <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr>
      <?php foreach ($staff as $s): ?>
        <tr>
          <td><?= e($s['name']) ?></td>
          <td><?= e($s['email']) ?></td>
          <td><?= e(ucfirst($s['role'])) ?></td>
          <td><?= (int) $s['active'] === 1 ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-muted">Deactivated</span>' ?></td>
          <td>
            <form method="post" class="inline-form">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-light btn-small" name="action" value="toggle"><?= (int) $s['active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
              <button class="btn btn-light btn-small" name="action" value="reset" onclick="return confirm('Reset this password to: password ?')">Reset password</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <p class="muted"><?= (int) $parentCount ?> parent account<?= (int) $parentCount === 1 ? '' : 's' ?> registered (parents sign up themselves).</p>
</div>

<div class="card">
  <h2>Add a staff member</h2>
  <form method="post" class="form">
    <input type="hidden" name="action" value="add">
    <div class="form-grid">
      <div><label for="name">Full name</label><input type="text" id="name" name="name" required></div>
      <div><label for="email">Email</label><input type="email" id="email" name="email" required></div>
      <div><label for="phone">Phone (optional)</label><input type="tel" id="phone" name="phone"></div>
      <div>
        <label for="role">Role</label>
        <select id="role" name="role" required>
          <option value="nurse">Nurse</option>
          <option value="caregiver">Caregiver</option>
          <option value="security">Security</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div>
        <label for="password">Password <span class="hint">(leave empty to use "password")</span></label>
        <input type="text" id="password" name="password" autocomplete="off">
      </div>
    </div>
    <p><button class="btn" type="submit">Create account</button></p>
  </form>
</div>

<?php page_footer(); ?>