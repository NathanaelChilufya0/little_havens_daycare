# Little Haven AI Daycare Centre: Handoff (updated after batch 2)

Context file for any model or teammate picking up this project. It records what is **decided**, what is **built**, what is **left**, and the **contracts** the remaining files must follow. Do not re-litigate the decided items.

## 1. Project in one paragraph
A group project for a tech expo presentation on **Wednesday 18 November 2026**. It is a PHP prototype of a daycare management system for Little Haven AI Daycare Centre in Zambia: children aged 1 to 4, about 10 children, staffed by a nurse, an early childhood educator or caregiver, a childcare assistant and a security guard. The goal is a smooth, demo-safe prototype that tells a clear story, not a complete product. Time is very short (built the evening before the presentation).

## 2. Decided
| Item | Decision |
|---|---|
| Name | **Little Haven** (not "Little Stars"; that name only appears in an AI-generated UI mockup) |
| Language | PHP 8.0 or newer (uses `match`), **plain PHP, no framework** (confirmed: user said go ahead) |
| Database | **MySQL** via PDO with prepared statements; XAMPP/WAMP assumed. `database.sql` must be imported into MySQL (phpMyAdmin Import tab) |
| Roles | **Parent, Nurse, Caregiver, Security, Admin** (5 roles), session-based login |
| Payments | **Simulated only** (fake "Pay now", fake reference number, saved to `payments`) |
| Security role | Child **check-in / check-out** plus pickup verification, both required |
| AI summary | Template/rule-based, labelled "AI-assisted care summary". No real LLM call |
| Look and feel | Purple theme, one stylesheet, system fonts only (works offline) |
| Delivery | Files are generated **4 at a time** at the user's request |

## 3. Build status
Project folder: `littlehaven/` (must sit in `htdocs/littlehaven` or change `BASE_URL` in `db.php`). **Nothing has been run yet** (the build sandbox had no PHP or MySQL), so test everything on the user's machine.

| Batch | Files | Status |
|---|---|---|
| 1 | `database.sql`, `db.php`, `auth.php`, `assets/style.css` | Written |
| 2 | `index.php`, `logout.php`, `setup.php`, `parent/dashboard.php` | Written |
| 3 | `parent/register_child.php`, `parent/pickups.php`, `parent/pay.php`, `nurse/dashboard.php` | To do |
| 4 | `nurse/incident.php`, `caregiver/dashboard.php`, `caregiver/log.php`, `security/dashboard.php` | To do |
| 5 | `security/pickup.php`, `admin/dashboard.php`, `admin/staff.php`, `admin/settings.php` | To do |
| 6 | `admin/logs.php`, `README.md` (setup steps and demo script) | To do |

The top navigation in `auth.php` (`nav_for()`) already links to every file above, so a missing file shows a 404 until its batch is written.

## 4. Setup (for the README)
1. Start Apache and MySQL in XAMPP/WAMP.
2. Copy the `littlehaven` folder into `htdocs`.
3. In phpMyAdmin, Import `database.sql` (creates the `littlehaven` database itself).
4. Open `http://localhost/littlehaven/`.
5. All demo accounts use the password `password`: `parent@`, `nurse@`, `caregiver@`, `security@`, `admin@` + `littlehaven.test`. Extra parents: `joyce@`, `daniel@`.
6. If login fails, open `/littlehaven/setup.php` once to reset the demo passwords.
7. To reset the demo data, re-import `database.sql`.

## 5. What each role does

**Parent** (website login)
- Registers child: name, age, class, emergency contact number, optional photo (cut if short on time)
- Enters allergies; sickness or condition; medicine name, dosage and time
- Ticks "I left medicine at home: Yes/No" and gives instructions with the dosage
- If medicine is left at home, the centre may supply it and the nurse confirms, **for an extra fee**. Parents are expected to bring medicine for their own children
- Adds authorised pickup people (name, relationship, phone, ID number)
- Sees the daily timeline (arrived, meals, medicine given, picked up), attendance and payment history, and pays the simulated fee

**Nurse**
- Checks the child's health on arrival
- Gives medicine exactly as the parent instructed (dosage and time) and logs it (time and nurse name)
- Keeps allergy and sickness records
- Handles first aid and emergencies, and logs incidents
- Confirms or declines centre-supplied medicine (extra fee)

**Caregiver**
- Looks after children daily: feeding, playing, sleeping, cleaning
- Sees the child list with a prominent red allergy banner and the nurse's notes for sick children
- Logs meals, naps and activities

**Security**
- Daily screen showing the expected children with **Check In** and **Check Out** buttons
- Check-in records who dropped the child and the time. Check-out records who collected the child and the time
- At pickup, shows the child's authorised list (name, relationship, phone, ID number) so the guard can compare against the person's physical ID
- Anyone not on the list gets a red **NOT AUTHORISED** block, and no child leaves with a stranger

**Admin**
- Creates and deactivates staff accounts
- Views all children, sets package prices and fees
- Views attendance and payment reports
- Views the activity log (useful as a safeguarding talking point)

## 6. Key logic (no real AI needed)
- **Allergy conflict alert:** allergies are comma-separated keywords in `health_profiles.allergies`. Each menu item has an `ingredients` list. `allergy_conflicts()` in `auth.php` compares them (substring match, so "egg" matches "eggs"). Already used on the parent dashboard.
- **Medicine due now:** medications with `time_due <= sim_time()` and no `medication_log` row today are DUE (within 30 minutes of the due time) or OVERDUE (later). **Mark as given** inserts a `medication_log` row. The nurse dashboard has a "simulate time" box that stores `$_SESSION['sim_time']` (format `HH:MM`) so the demo never waits. `sim_time()` in `auth.php` reads it, falling back to the real time.
- **Pickup verification:** compare the person's ID number (trimmed, case-insensitive) against `authorised_pickups.id_number` for that child. No match: block and show "NOT AUTHORISED".
- **Care summary:** `build_care_summary($childId, $date)` fills a sentence template from attendance, `care_log`, `medication_log` and `incidents`. Already used on the parent dashboard.

## 7. Safeguards (say these out loud in the presentation)
- AI never diagnoses, prescribes or suggests doses
- The nurse gives only what the parent instructed
- Technology supports trained staff and procedures and does not replace them. It reduces risk and improves accountability, and does not eliminate abuse or kidnapping
- Legal grounding: Children's Code Act No. 12 of 2022 (Zambia): child care facilities need Director of Social Welfare approval (ss. 268 and 269); s. 38 on parental responsibility

## 8. Database (12 tables, in `database.sql`)
The original plan said about 9; three tables were added for features the roles need.
`users(role)`, `packages`, `settings(k, v)` (holds `medicine_fee`), `children`, `health_profiles`, `medications(left_at_home, centre_supplied, fee, nurse_status)`, `medication_log`, `authorised_pickups`, `attendance(check_in, check_out, dropped_by, picked_by, pickup_id, checked_in_by, checked_out_by; unique per child per day)`, `menu_items(day_of_week 0 = every day, ingredients)`, `care_log(log_type meal|nap|activity|cleaning)`, `incidents`, `payments(reference, status pending|paid)`, `activity_log`.

`medications.nurse_status`: `not_needed` (parent sent medicine), `pending` (parent asked the centre to supply it), `confirmed`, `declined`.

Seed data: 7 users (one per role plus two extra parents), 8 children, Chipo Ng'ambi (id 1, age 3, egg allergy, asthma, inhaler due 10:00, left at home, centre-supplied and confirmed), two authorised pickups for Chipo (Mary Ng'ambi, ID `123456/10/1`; Joseph Ng'ambi, ID `234567/10/1`), a menu with an egg lunch every day, past attendance and payments, and one pending payment for the demo parent. Nobody is checked in today, so the security guard checks Chipo in during the demo.

## 9. Conventions for the remaining files (follow these)
- Every page starts `require_once __DIR__ . '/../auth.php'; $user = require_role('nurse');` then `page_header('Title');` and ends with `page_footer();`.
- Database helpers from `db.php`: `q()` rows, `q1()` one row, `run()` write, `insert()` write and return id. Always prepared statements.
- Output escaping: always `e()`. Links: `url('path')`. Redirect: `redirect('path')`. Messages: `set_flash($msg, 'ok'|'warn'|'error')` before redirecting.
- Dates and times: store PHP `date('Y-m-d H:i:s')` (not MySQL `NOW()`), so everything uses the Africa/Lusaka timezone set in `db.php`.
- Write actions call `log_activity('...')` so the admin log is useful.
- Writes are POST forms followed by redirect (Post/Redirect/Get). No CSRF tokens (prototype; mention as a "next step" if asked).
- CSS classes available: `card`, `card accent`, `grid`, `row`, `stack`, `btn` (+ `btn-small`, `btn-green`, `btn-danger`, `btn-light`), `badge` (+ `badge-ok|due|overdue|info|muted`), `alert-allergy`, `alert-conflict`, `alert-block` (NOT AUTHORISED), `alert-allowed`, `timeline`, `summary`, `stats`/`stat`, `table-wrap`, `form`, `check`, `form-grid`.
- Helpers in `auth.php`: `todays_menu()`, `keywords()`, `allergy_conflicts()`, `attendance_for()`, `child_timeline()`, `build_care_summary()`, `sim_time()`, `first_name()`, `kwacha()`.

## 10. Contracts for files still to write
- **`parent/register_child.php`**: one form creating `children` + `health_profiles`, optional first `medications` row, and optional first `authorised_pickups` row. If "left at home" is ticked and "ask the centre to supply it" is ticked: `centre_supplied=1`, `nurse_status='pending'`, `fee` = `settings.medicine_fee`. Also creates a `pending` payment for the package price (plus the medicine fee). Photo upload is cut.
- **`parent/pickups.php?child=ID`**: pick one of the parent's own children (check `parent_id`), list, add and remove authorised pickups.
- **`parent/pay.php`**: list all of the parent's payments; "Pay now" on a pending row sets `status='paid'`, `paid_at`, and a fake reference like `LH-YYYYMMDD-XXXX`. Label it "Simulated payment".
- **`nurse/dashboard.php`**: simulate-time form; medicines due (DUE/OVERDUE) for children who are checked in today, with "Mark as given" (inserts `medication_log` with nurse id and name); confirm/decline centre-supplied medicine (`nurse_status` pending to confirmed or declined); checked-in children with allergies and an editable `nurse_notes`.
- **`nurse/incident.php`**: form (child, description, action taken) and a list of incidents.
- **`caregiver/dashboard.php`**: children checked in today, red `alert-allergy` banner, `alert-conflict` for menu clashes, the nurse's notes, and a link to `caregiver/log.php?child=ID`.
- **`caregiver/log.php?child=ID`**: log a meal, nap, activity or cleaning into `care_log`; show the child's timeline.
- **`security/dashboard.php`**: all active children with status; "Check In" form asking who dropped the child (inserts `attendance`); "Check Out" link to `security/pickup.php?child=ID`.
- **`security/pickup.php?child=ID`**: show the authorised list (name, relationship, phone, ID number). The guard types the collector's name and ID number. Match on ID number: found shows green `alert-allowed` and a "Release child" button that sets `check_out`, `picked_by`, `pickup_id`, `checked_out_by`; not found shows red `alert-block` "NOT AUTHORISED" and logs the blocked attempt in `activity_log`. Never release without a match.
- **`admin/dashboard.php`**: stat tiles (children, checked in today, payments received, pending), attendance table, payments table, all children list.
- **`admin/staff.php`**: create staff accounts (nurse, caregiver, security, admin) with `password_hash()`, deactivate/reactivate.
- **`admin/settings.php`**: edit package prices and `medicine_fee`.
- **`admin/logs.php`**: latest 100 `activity_log` rows.

## 11. 5-minute demo script
1. Parent registers a new child (for example "Mwila"; Chipo is already seeded), adds an egg allergy and a medicine, ticks "left at home".
2. Security checks Chipo in. Nurse logs in, sets simulated time to 10:00, sees the dose due, marks it given.
3. Caregiver sees Chipo's red allergy banner and the egg-meal conflict.
4. Security tries to release Chipo to an unlisted person (blocked), then to an authorised person (allowed).
5. Parent refreshes and sees the full timeline and care summary, and pays the simulated fee.
6. Admin shows attendance and payment totals and the activity log.

## 12. Cut if short on time
Photo upload, real payments (mobile money), real AI, email/SMS, multi-centre support, CSRF tokens.

## 13. Open questions
- Package names and fee amounts (placeholders are in the seed: Half Day K600, Full Day K1000, Full Day Plus K1300, medicine fee K50).
- Which of the group presents which section.
