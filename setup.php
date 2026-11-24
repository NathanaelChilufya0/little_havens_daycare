<?php
// Safety net for the demo: resets the seeded demo accounts to the password "password".
// Open http://localhost/littlehaven/setup.php once if login fails after importing database.sql.
// Delete this file before any real use. It only touches the seeded demo emails.

require_once __DIR__ . '/db.php';

$emails = [
    'parent@littlehaven.test', 'nurse@littlehaven.test', 'caregiver@littlehaven.test',
    'security@littlehaven.test', 'admin@littlehaven.test',
    'joyce@littlehaven.test', 'daniel@littlehaven.test',
];

$hash = password_hash('password', PASSWORD_DEFAULT);
$done = 0;
foreach ($emails as $email) {
    $done += run('UPDATE users SET password_hash = ?, active = 1 WHERE email = ?', [$hash, $email]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Little Heaven setup</title>
<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL) ?>/assets/style.css">
</head>
<body class="guest">
<main class="page">
  <div class="login-wrap">
    <div class="card">
      <h2>Demo passwords reset</h2>
      <p><?= (int) $done ?> demo accounts now use the password <strong>password</strong>.</p>
      <p><a class="btn" href="<?= htmlspecialchars(BASE_URL) ?>/index.php">Go to login</a></p>
    </div>
  </div>
</main>
</body>
</html>
