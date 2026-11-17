<?php
// Little Haven: session login, role guards, page layout and shared helpers.
// Every page starts with:  require_once __DIR__ . '/../auth.php';  (or './auth.php' at the top level)

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ------------------------------------------------------------------ basics */

/** Escape text for safe output in HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function kwacha($amount): string
{
    return 'K' . number_format((float) $amount, 2);
}

function set_flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function get_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ------------------------------------------------------------------- login */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function role_home(string $role): string
{
    return match ($role) {
        'parent'    => 'parent/dashboard.php',
        'nurse'     => 'nurse/dashboard.php',
        'caregiver' => 'caregiver/dashboard.php',
        'security'  => 'security/dashboard.php',
        'admin'     => 'admin/dashboard.php',
        default     => 'index.php',
    };
}

function attempt_login(string $email, string $password): bool
{
    $u = q1('SELECT * FROM users WHERE email = ? AND active = 1', [strtolower(trim($email))]);
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'   => (int) $u['id'],
            'name' => $u['name'],
            'role' => $u['role'],
        ];
        log_activity('Logged in');
        return true;
    }
    return false;
}

/** Block the page unless the person is logged in with one of the allowed roles. */
function require_role(string ...$roles): array
{
    $u = current_user();
    if (!$u) {
        redirect('index.php');
    }
    if (!in_array($u['role'], $roles, true)) {
        set_flash('That page is for another role. You were taken back to your own screen.', 'warn');
        redirect(role_home($u['role']));
    }
    return $u;
}

function log_activity(string $action): void
{
    $u = current_user();
    run(
        'INSERT INTO activity_log (user_id, user_name, role, action, created_at) VALUES (?,?,?,?,?)',
        [$u['id'] ?? null, $u['name'] ?? 'Guest', $u['role'] ?? null, $action, date('Y-m-d H:i:s')]
    );
}

/* ------------------------------------------------------------------ layout */

function nav_for(string $role): array
{
    return match ($role) {
        'parent' => [
            ['My children', 'parent/dashboard.php'],
            ['Register a child', 'parent/register_child.php'],
            ['Pickup people', 'parent/pickups.php'],
            ['Payments', 'parent/pay.php'],
        ],
        'nurse' => [
            ['Health desk', 'nurse/dashboard.php'],
            ['Incidents', 'nurse/incident.php'],
        ],
        'caregiver' => [
            ['Today\'s children', 'caregiver/dashboard.php'],
        ],
        'security' => [
            ['Gate', 'security/dashboard.php'],
        ],
        'admin' => [
            ['Overview', 'admin/dashboard.php'],
            ['Staff', 'admin/staff.php'],
            ['Fees and packages', 'admin/settings.php'],
            ['Activity log', 'admin/logs.php'],
        ],
        default => [],
    };
}

function page_header(string $title): void
{
    $u = current_user();
    $flash = get_flash();
    $current = ltrim(str_replace(BASE_URL, '', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)), '/');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Little Haven</title>
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body<?= $u ? ' class="role-' . e($u['role']) . '"' : ' class="guest"' ?>>
<header class="topbar">
  <a class="brand" href="<?= e($u ? url(role_home($u['role'])) : url('index.php')) ?>">
    <span class="brand-mark" aria-hidden="true">LH</span>
    <span>Little Haven</span>
  </a>
  <?php if ($u): ?>
  <nav class="nav" aria-label="Main">
    <?php foreach (nav_for($u['role']) as [$label, $path]): ?>
      <a href="<?= e(url($path)) ?>"<?= $current === $path ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="whoami">
    <span><?= e($u['name']) ?> <small>(<?= e($u['role']) ?>)</small></span>
    <a class="btn btn-small btn-ghost" href="<?= e(url('logout.php')) ?>">Log out</a>
  </div>
  <?php endif; ?>
</header>
<main class="page">
  <?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
  <?php endif; ?>
  <h1 class="page-title"><?= e($title) ?></h1>
<?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="footer">
  Little Haven prototype. Technology supports trained staff and does not replace them.
  The care summary is rule-based: it never diagnoses, prescribes or suggests doses.
</footer>
</body>
</html>
<?php
}

/* ----------------------------------------------------------- child helpers */

/** Time used for "medicine due" checks. The nurse can simulate a time so the demo never waits. */
function sim_time(): string
{
    return $_SESSION['sim_time'] ?? date('H:i');
}

function first_name(string $full): string
{
    return explode(' ', trim($full))[0];
}

/** Menu items for today (day_of_week 0 means served every day). */
function todays_menu(): array
{
    return q(
        "SELECT * FROM menu_items WHERE day_of_week IN (0, ?)
         ORDER BY FIELD(meal_type, 'breakfast', 'lunch', 'snack')",
        [(int) date('N')]
    );
}

/** Split "egg, Peanut ,milk" into ['egg', 'peanut', 'milk']. */
function keywords(?string $text): array
{
    if ($text === null || trim($text) === '') {
        return [];
    }
    $parts = array_map(fn($w) => strtolower(trim($w)), explode(',', $text));
    return array_values(array_filter($parts, fn($w) => $w !== ''));
}

/**
 * Compare a child's allergy keywords with each meal's ingredients.
 * Returns a list of ['allergen' => 'egg', 'meal' => 'Lunch: Nshima, egg relish and rape'].
 */
function allergy_conflicts(?string $allergies, array $menu): array
{
    $conflicts = [];
    foreach (keywords($allergies) as $allergen) {
        foreach ($menu as $m) {
            if (stripos($m['ingredients'], $allergen) !== false) {
                $conflicts[] = [
                    'allergen' => $allergen,
                    'meal'     => ucfirst($m['meal_type']) . ': ' . $m['name'],
                ];
            }
        }
    }
    return $conflicts;
}

/** Attendance row for a child on a date, or null. */
function attendance_for(int $childId, string $date): ?array
{
    return q1('SELECT * FROM attendance WHERE child_id = ? AND att_date = ?', [$childId, $date]);
}

/**
 * Everything that happened to a child on a date, oldest first.
 * Each item: ['time' => 'Y-m-d H:i:s', 'type' => 'arrival|meal|nap|activity|cleaning|medicine|incident|pickup', 'text' => '...']
 */
function child_timeline(int $childId, string $date): array
{
    $items = [];

    $att = attendance_for($childId, $date);
    if ($att && $att['check_in']) {
        $items[] = ['time' => $att['check_in'], 'type' => 'arrival',
                    'text' => 'Arrived at the centre, dropped off by ' . $att['dropped_by']];
    }
    if ($att && $att['check_out']) {
        $items[] = ['time' => $att['check_out'], 'type' => 'pickup',
                    'text' => 'Collected by ' . $att['picked_by']];
    }

    foreach (q('SELECT * FROM care_log WHERE child_id = ? AND DATE(logged_at) = ?', [$childId, $date]) as $r) {
        $items[] = ['time' => $r['logged_at'], 'type' => $r['log_type'],
                    'text' => ucfirst($r['log_type']) . ($r['details'] ? ': ' . $r['details'] : '')
                              . ' (' . $r['staff_name'] . ')'];
    }

    foreach (q(
        'SELECT l.given_at, l.nurse_name, m.name, m.dosage
         FROM medication_log l JOIN medications m ON m.id = l.medication_id
         WHERE l.child_id = ? AND DATE(l.given_at) = ?',
        [$childId, $date]
    ) as $r) {
        $items[] = ['time' => $r['given_at'], 'type' => 'medicine',
                    'text' => 'Medicine given: ' . $r['name'] . ', ' . $r['dosage'] . ' (' . $r['nurse_name'] . ')'];
    }

    foreach (q('SELECT created_at, nurse_name FROM incidents WHERE child_id = ? AND DATE(created_at) = ?', [$childId, $date]) as $r) {
        $items[] = ['time' => $r['created_at'], 'type' => 'incident',
                    'text' => 'Incident recorded by ' . $r['nurse_name'] . '. The nurse will speak to you.'];
    }

    usort($items, fn($a, $b) => strcmp($a['time'], $b['time']));
    return $items;
}

/**
 * "AI-assisted care summary": a sentence template filled from the day's records.
 * No model is called, so it works offline and can never invent medical advice.
 */
function build_care_summary(int $childId, string $date): string
{
    $child = q1('SELECT name FROM children WHERE id = ?', [$childId]);
    if (!$child) {
        return '';
    }
    $n = first_name($child['name']);

    $att = attendance_for($childId, $date);
    if (!$att || !$att['check_in']) {
        return "$n has not arrived at the centre yet today.";
    }

    $s = [];
    $s[] = "$n arrived at " . date('H:i', strtotime($att['check_in']))
         . ' and was dropped off by ' . $att['dropped_by'] . '.';

    $byType = ['meal' => [], 'nap' => [], 'activity' => [], 'cleaning' => []];
    foreach (q('SELECT * FROM care_log WHERE child_id = ? AND DATE(logged_at) = ? ORDER BY logged_at', [$childId, $date]) as $r) {
        $byType[$r['log_type']][] = ($r['details'] ?: ucfirst($r['log_type'])) . ' (' . date('H:i', strtotime($r['logged_at'])) . ')';
    }
    if ($byType['meal'])     { $s[] = 'Meals: ' . implode('; ', $byType['meal']) . '.'; }
    if ($byType['nap'])      { $s[] = 'Nap: ' . implode('; ', $byType['nap']) . '.'; }
    if ($byType['activity']) { $s[] = 'Activities: ' . implode('; ', $byType['activity']) . '.'; }

    $given = q(
        'SELECT l.given_at, l.nurse_name, m.name, m.dosage
         FROM medication_log l JOIN medications m ON m.id = l.medication_id
         WHERE l.child_id = ? AND DATE(l.given_at) = ? ORDER BY l.given_at',
        [$childId, $date]
    );
    foreach ($given as $g) {
        $s[] = 'Medicine given: ' . $g['name'] . ', ' . $g['dosage'] . ' at '
             . date('H:i', strtotime($g['given_at'])) . ' by ' . $g['nurse_name'] . '.';
    }
    $pending = q(
        'SELECT m.name FROM medications m
         WHERE m.child_id = ? AND m.active = 1 AND m.nurse_status <> "declined"
           AND NOT EXISTS (SELECT 1 FROM medication_log l WHERE l.medication_id = m.id AND DATE(l.given_at) = ?)',
        [$childId, $date]
    );
    foreach ($pending as $p) {
        $s[] = 'Medicine not yet given today: ' . $p['name'] . '.';
    }

    if (q1('SELECT id FROM incidents WHERE child_id = ? AND DATE(created_at) = ?', [$childId, $date])) {
        $s[] = 'An incident was recorded by the nurse today. Please speak to the nurse at pickup.';
    }
    if ($att['check_out']) {
        $s[] = "$n was collected at " . date('H:i', strtotime($att['check_out'])) . ' by ' . $att['picked_by'] . '.';
    }
    return implode(' ', $s);
}
