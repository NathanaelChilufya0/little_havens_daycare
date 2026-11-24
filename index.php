<?php
require_once __DIR__ . '/auth.php';

// Already logged in? Go straight to your own screen.
if ($u = current_user()) {
    redirect(role_home($u['role']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        redirect(role_home(current_user()['role']));
    }
    $error = 'That email and password do not match an active account.';
}

$demo = [
    ['Parent',    'parent@littlehaven.test'],
    ['Nurse',     'nurse@littlehaven.test'],
    ['Caregiver', 'caregiver@littlehaven.test'],
    ['Security',  'security@littlehaven.test'],
    ['Admin',     'admin@littlehaven.test'],
];

page_header('Welcome to Little Heaven');
?>
<div class="login-wrap">
  <div class="card">
    <h2>Log in</h2>
    <?php if ($error): ?>
      <div class="flash flash-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form" autocomplete="off">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <p><button class="btn" type="submit">Log in</button></p>
      <p class="muted">New parent? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    </form>
  </div>

  <div class="card">
    <h3>Demo accounts</h3>
    <p class="muted">Every account uses the password <strong>password</strong>. Click a role to fill the form.</p>
    <table class="demo-list">
      <?php foreach ($demo as [$role, $email]): ?>
        <tr>
          <td><?= e($role) ?></td>
          <td class="muted"><?= e($email) ?></td>
          <td><button type="button" class="btn btn-small btn-light"
                      onclick="document.getElementById('email').value='<?= e($email) ?>';document.getElementById('password').value='password';">Use</button></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php page_footer(); ?>
