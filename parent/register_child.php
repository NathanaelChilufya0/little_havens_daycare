<?php
require_once __DIR__ . '/../extra.php';

$user = require_role('parent');

// Look up (or add) the columns this form needs BEFORE any transaction starts.
$emergencyCol = col_or_add('children', ['emergency_phone', 'emergency_contact', 'emergency_number'], 'VARCHAR(30) NULL');
$emergencyNm  = col_or_add('children', ['emergency_name'], 'VARCHAR(120) NULL');
$instrCol     = col_or_add('medications', ['instructions', 'parent_instructions', 'special_instructions'], 'TEXT NULL');
col_or_add('authorised_pickups', ['phone'], 'VARCHAR(30) NULL');
$feeCol       = col_or_add('packages', ['monthly_fee', 'fee', 'price', 'cost', 'amount'], 'DECIMAL(10,2) NOT NULL DEFAULT 0');

$packages = q('SELECT * FROM packages ORDER BY id');
$classes  = ['Buds' => 'Buds (1 year)', 'Seedlings' => 'Seedlings (2 years)', 'Sunflowers' => 'Sunflowers (3 years)', 'Explorers' => 'Explorers (4 years)'];
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = posted('child_name');
    $age       = (int) posted('age');
    $class     = posted('class_name');
    $pkg       = (int) posted('package_id');
    $ePhone    = posted('emergency_phone');
    $eName     = posted('emergency_name');
    $allergies = posted('allergies');
    $condition = posted('condition_notes');
    $medName   = posted('med_name');
    $medDose   = posted('med_dosage');
    $medTime   = posted('med_time');
    $atHome    = posted('left_at_home') === '1' ? 1 : 0;
    $supply    = ($atHome && isset($_POST['centre_supplied'])) ? 1 : 0;
    $instr     = posted('med_instructions');
    $pName     = posted('pickup_name');
    $pRel      = posted('pickup_relationship');
    $pId       = posted('pickup_id');
    $pPhone    = posted('pickup_phone');
    
    // Keep text inside the database column sizes.
    $name = mb_substr($name, 0, 100);       $eName = mb_substr($eName, 0, 100);
    $allergies = mb_substr($allergies, 0, 255); $condition = mb_substr($condition, 0, 500);
    $medName = mb_substr($medName, 0, 100); $medDose = mb_substr($medDose, 0, 100); $instr = mb_substr($instr, 0, 300);
    $pName = mb_substr($pName, 0, 100);     $pRel = mb_substr($pRel, 0, 50);
    $pId = mb_substr($pId, 0, 30);          $pPhone = mb_substr($pPhone, 0, 30);

    if ($name === '')                                   { $errors[] = "Enter the child's name."; }
    if ($age < 1 || $age > 6)                           { $errors[] = 'Enter the age in years (1 to 6).'; }
    if ($class === '')                                  { $errors[] = 'Choose a class.'; }
    if (!q1('SELECT id FROM packages WHERE id = ?', [$pkg])) { $errors[] = 'Choose a childcare package.'; }
    if (strlen(preg_replace('/\D+/', '', $ePhone)) < 9) { $errors[] = 'Enter an emergency phone number.'; }
    if ($medName !== '' && ($medDose === '' || $medTime === '')) {
        $errors[] = 'For the medicine, enter both the dosage and the time to give it.';
    }
    if ($pName !== '' && ($pRel === '' || $pId === '')) {
        $errors[] = 'For the pickup person, enter their relationship and ID number.';
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $childId = insert_row('children', [
                'parent_id'    => $user['id'],
                'name'         => $name,
                'age'          => $age,
                'class_name'   => $class,
                'package_id'   => $pkg,
                'active'       => 1,
                $emergencyCol  => $ePhone,
                $emergencyNm   => $eName,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            insert_row('health_profiles', [
                'child_id'        => $childId,
                'allergies'       => is_none($allergies) ? '' : $allergies,
                'condition_notes' => is_none($condition) ? '' : $condition,
            ]);

            if ($medName !== '') {
                $med = [
                    'child_id'      => $childId,
                    'name'          => $medName,
                    'dosage'        => $medDose,
                    'time_due'      => $medTime,
                    'left_at_home'  => $atHome,
                    'centre_supplied' => $supply,
                    'fee'           => $supply ? CENTRE_MEDICINE_FEE : 0,
                    'active'        => 1,
                    $instrCol       => $instr,
                ];
                if ($supply) {
                    $med['nurse_status'] = 'pending';
                }
                try {
                    insert_row('medications', $med);
                } catch (PDOException $e) {
                    // Some databases need an explicit nurse_status value; try the neutral one.
                    $med['nurse_status'] = $med['nurse_status'] ?? 'none';
                    insert_row('medications', $med);
                }
            }

            if ($pName !== '') {
                insert_row('authorised_pickups', [
                    'child_id' => $childId, 'name' => $pName, 'relationship' => $pRel,
                    'phone' => $pPhone, 'id_number' => $pId,
                ]);
            }

            $pdo->commit();
            log_activity('Registered child ' . $name);
            set_flash($name . ' is registered. Welcome to Little Haven!');
            redirect('parent/dashboard.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not save the registration: ' . $e->getMessage();
        }
    }
}

// Keeps what the parent typed if the form comes back with errors.
function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

page_header('Register a child');
?>

<?php foreach ($errors as $err): ?>
  <div class="flash flash-error" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="form" autocomplete="off">

  <section class="card accent">
    <h2>1. Child details</h2>
    <div class="form-grid">
      <div><label for="child_name">Child's name</label><input type="text" id="child_name" name="child_name" value="<?= old('child_name') ?>" required></div>
      <div><label for="age">Age (years)</label><input type="number" id="age" name="age" min="1" max="6" value="<?= old('age') ?>" required></div>
      <div>
        <label for="class_name">Class</label>
        <select id="class_name" name="class_name" required>
          <option value="">Choose a class</option>
          <?php foreach ($classes as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= ($_POST['class_name'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="package_id">Childcare package</label>
        <select id="package_id" name="package_id" required>
          <option value="">Choose a package</option>
          <?php foreach ($packages as $p): ?>
            <option value="<?= (int) $p['id'] ?>"<?= (int) ($_POST['package_id'] ?? 0) === (int) $p['id'] ? ' selected' : '' ?>>
              <?= e($p['name']) ?> (<?= e(kwacha($p[$feeCol])) ?> per month)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label for="emergency_name">Emergency contact name</label><input type="text" id="emergency_name" name="emergency_name" value="<?= old('emergency_name') ?>"></div>
      <div><label for="emergency_phone">Emergency contact number</label><input type="tel" id="emergency_phone" name="emergency_phone" value="<?= old('emergency_phone') ?>" required></div>
    </div>
  </section>

  <section class="card accent">
    <h2>2. Health information</h2>
    <label for="allergies">Allergies <span class="hint">(separate with commas, e.g. egg, peanut. Write "none" if there are none.)</span></label>
    <input type="text" id="allergies" name="allergies" value="<?= old('allergies') ?>">
    <label for="condition_notes">Sickness or condition <span class="hint">(anything the nurse should know)</span></label>
    <textarea id="condition_notes" name="condition_notes"><?= old('condition_notes') ?></textarea>
  </section>

  <section class="card accent">
    <h2>3. Medicine (optional)</h2>
    <p class="muted">The nurse gives medicine exactly as you write it here. Parents should normally bring their own child's medicine.</p>
    <div class="form-grid">
      <div><label for="med_name">Medicine name</label><input type="text" id="med_name" name="med_name" value="<?= old('med_name') ?>"></div>
      <div><label for="med_dosage">Dosage <span class="hint">(e.g. 5 ml)</span></label><input type="text" id="med_dosage" name="med_dosage" value="<?= old('med_dosage') ?>"></div>
      <div><label for="med_time">Time to give</label><input type="time" id="med_time" name="med_time" value="<?= old('med_time') ?>"></div>
    </div>
    <label for="med_instructions">Your instructions <span class="hint">(e.g. give after food, shake well)</span></label>
    <textarea id="med_instructions" name="med_instructions"><?= old('med_instructions') ?></textarea>

    <label>I left the medicine at home</label>
    <div class="row">
      <label class="check"><input type="radio" name="left_at_home" value="0"<?= posted('left_at_home') !== '1' ? ' checked' : '' ?>> No, I will bring it</label>
      <label class="check"><input type="radio" name="left_at_home" value="1"<?= posted('left_at_home') === '1' ? ' checked' : '' ?>> Yes, I left it at home</label>
    </div>
    <label class="check">
      <input type="checkbox" name="centre_supplied" value="1"<?= isset($_POST['centre_supplied']) ? ' checked' : '' ?>>
      If I left it at home, ask the centre to supply it (extra fee <?= e(kwacha(CENTRE_MEDICINE_FEE)) ?>, only if the nurse has that medicine)
    </label>
  </section>

  <section class="card accent">
    <h2>4. Who may collect your child (optional now)</h2>
    <p class="muted">You can add more people later under "Pickup people".</p>
    <div class="form-grid">
      <div><label for="pickup_name">Full name</label><input type="text" id="pickup_name" name="pickup_name" value="<?= old('pickup_name') ?>"></div>
      <div><label for="pickup_relationship">Relationship</label><input type="text" id="pickup_relationship" name="pickup_relationship" value="<?= old('pickup_relationship') ?>"></div>
      <div><label for="pickup_id">NRC / ID number</label><input type="text" id="pickup_id" name="pickup_id" value="<?= old('pickup_id') ?>"></div>
      <div><label for="pickup_phone">Phone</label><input type="tel" id="pickup_phone" name="pickup_phone" value="<?= old('pickup_phone') ?>"></div>
    </div>
  </section>

  <p class="row">
    <button class="btn" type="submit">Register child</button>
    <a class="btn btn-light" href="<?= e(url('parent/dashboard.php')) ?>">Cancel</a>
  </p>
</form>

<?php page_footer(); ?>