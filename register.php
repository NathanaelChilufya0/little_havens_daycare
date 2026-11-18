<?php
require_once __DIR__ . '/extra.php';

// Already logged in? Go to your own screen.
if ($u = current_user()) {
    redirect(role_home($u['role']));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = mb_substr(posted('name'), 0, 100);
    $email = strtolower(posted('email'));
    $phone = mb_substr(posted('phone'), 0, 30);
    $pass  = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $pass2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';

    if ($name === '') {
        $errors[] = 'Enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen(preg_replace('/\D+/', '', $phone)) < 9) {
        $errors[] = 'Enter your phone number.';
    }
    if (strlen($pass) < 6) {
        $errors[] = 'The password must be at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $errors[] = 'The two passwords do not match.';
    }
    if (!$errors && q1('SELECT id FROM users WHERE email = ?', [$email])) {
        $errors[] = 'That email address already has an account. Try logging in instead.';
    }

    if (!$errors) {
        insert_row('users', [
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'role'          => 'parent',
            'active'        => 1,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        attempt_login($email, $pass);          // logs the new parent straight in
        log_activity('Parent account created');
        set_flash('Welcome to Little Haven! Now tell us about your child.');
        redirect('parent/register_child.php');
    }
}

page_header('Create your parent account');
?>
<div class="login-wrap">
  <div class="card">
    <h2>Join Little Haven</h2>
    <?php foreach ($errors as $err): ?>
      <div class="flash flash-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" class="form" autocomplete="off">
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
      <label for="phone">Phone number</label>
      <input type="tel" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" required>
      <label for="password">Password <span class="hint">(at least 6 characters)</span></label>
      <input type="password" id="password" name="password" required>
      <label for="password2">Repeat password</label>
      <input type="password" id="password2" name="password2" required>
      <p><button class="btn" type="submit">Create account</button></p>
      <p class="muted">Already have an account? <a href="<?= e(url('index.php')) ?>">Log in</a></p>
    </form>
  </div>
</div>
<?php page_footer(); ?>