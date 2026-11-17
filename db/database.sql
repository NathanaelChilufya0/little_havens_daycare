-- Little Haven AI Daycare Centre: schema + seed data
-- Import in phpMyAdmin (Import tab) or: mysql -u root < database.sql
-- Re-importing wipes and rebuilds everything, so it is the "reset" button for the demo.

DROP DATABASE IF EXISTS littlehaven;
CREATE DATABASE littlehaven CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE littlehaven;

-- ---------------------------------------------------------------- tables

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('parent','nurse','caregiver','security','admin') NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  price DECIMAL(10,2) NOT NULL,          -- price per month, in Kwacha
  description VARCHAR(200) DEFAULT NULL
);

CREATE TABLE settings (
  k VARCHAR(50) PRIMARY KEY,
  v VARCHAR(200) NOT NULL
);

CREATE TABLE children (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  package_id INT DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  age TINYINT NOT NULL,
  class_name VARCHAR(40) NOT NULL,
  emergency_contact VARCHAR(30) NOT NULL,
  photo VARCHAR(200) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES users(id),
  FOREIGN KEY (package_id) REFERENCES packages(id)
);

CREATE TABLE health_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  child_id INT NOT NULL UNIQUE,
  allergies VARCHAR(255) DEFAULT NULL,      -- comma separated keywords, e.g. "egg, peanut"
  condition_notes VARCHAR(500) DEFAULT NULL, -- sickness / condition from the parent
  nurse_notes VARCHAR(500) DEFAULT NULL,     -- written by the nurse
  FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE medications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  child_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  dosage VARCHAR(100) NOT NULL,
  time_due TIME NOT NULL,
  instructions VARCHAR(300) DEFAULT NULL,
  left_at_home TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = parent did NOT send medicine
  centre_supplied TINYINT(1) NOT NULL DEFAULT 0, -- 1 = centre supplies it (extra fee)
  fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  nurse_status ENUM('not_needed','pending','confirmed','declined') NOT NULL DEFAULT 'not_needed',
  active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE medication_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  medication_id INT NOT NULL,
  child_id INT NOT NULL,
  given_at DATETIME NOT NULL,
  nurse_id INT NOT NULL,
  nurse_name VARCHAR(100) NOT NULL,
  FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE CASCADE,
  FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE authorised_pickups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  child_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  relationship VARCHAR(50) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  id_number VARCHAR(30) NOT NULL,
  FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  child_id INT NOT NULL,
  att_date DATE NOT NULL,
  check_in DATETIME DEFAULT NULL,
  check_out DATETIME DEFAULT NULL,
  dropped_by VARCHAR(100) DEFAULT NULL,
  picked_by VARCHAR(100) DEFAULT NULL,
  pickup_id INT DEFAULT NULL,               -- authorised_pickups.id that was matched
  checked_in_by VARCHAR(100) DEFAULT NULL,  -- guard name
  checked_out_by VARCHAR(100) DEFAULT NULL,
  UNIQUE KEY one_per_day (child_id, att_date),
  FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  day_of_week TINYINT NOT NULL DEFAULT 0,   -- 0 = every day, 1 = Monday ... 7 = Sunday
  meal_type ENUM('breakfast','lunch','snack') NOT NULL,
  name VARCHAR(120) NOT NULL,
  ingredients VARCHAR(255) NOT NULL         -- comma separated, compared with allergy keywords
);

CREATE TABLE care_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  child_id INT NOT NULL,
  log_type ENUM('meal','nap','activity','cleaning') NOT NULL,
  details VARCHAR(255) DEFAULT NULL,
  logged_at DATETIME NOT NULL,
  staff_id INT NOT NULL,
  staff_name VARCHAR(100) NOT NULL,
  FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  child_id INT NOT NULL,
  nurse_id INT NOT NULL,
  nurse_name VARCHAR(100) NOT NULL,
  description VARCHAR(500) NOT NULL,
  action_taken VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  child_id INT DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(200) NOT NULL,
  reference VARCHAR(40) NOT NULL,           -- fake reference number
  status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  paid_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES users(id)
);

CREATE TABLE activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  user_name VARCHAR(100) NOT NULL,
  role VARCHAR(20) DEFAULT NULL,
  action VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL
);

-- ---------------------------------------------------------------- seed data
-- Every demo account uses the password:  password
-- (bcrypt hash below. If login fails, open /littlehaven/setup.php once to reset the demo passwords.)

INSERT INTO users (id, name, email, password_hash, role, phone) VALUES
(1, 'Mary Ng''ambi',   'parent@littlehaven.test',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent',    '0977 100 001'),
(2, 'Nurse Grace Phiri','nurse@littlehaven.test',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nurse',     '0977 100 002'),
(3, 'Ms Tendai Banda', 'caregiver@littlehaven.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'caregiver', '0977 100 003'),
(4, 'Mr Peter Zulu',   'security@littlehaven.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'security',  '0977 100 004'),
(5, 'Admin Mutale',    'admin@littlehaven.test',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',     '0977 100 005'),
(6, 'Joyce Mwansa',    'joyce@littlehaven.test',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent',    '0977 100 006'),
(7, 'Daniel Tembo',    'daniel@littlehaven.test',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent',    '0977 100 007');

INSERT INTO packages (id, name, price, description) VALUES
(1, 'Half Day',  600.00,  'Mornings, 07:30 to 12:30, with breakfast and lunch'),
(2, 'Full Day',  1000.00, 'Full day, 07:30 to 17:00, with all meals and a nap'),
(3, 'Full Day Plus', 1300.00, 'Full day with extended hours until 18:00');

INSERT INTO settings (k, v) VALUES
('medicine_fee', '50.00'),      -- extra fee when the centre supplies medicine
('centre_name',  'Little Haven AI Daycare Centre');

INSERT INTO children (id, parent_id, package_id, name, age, class_name, emergency_contact) VALUES
(1, 1, 2, 'Chipo Ng''ambi', 3, 'Sunflowers', '0977 100 001'),
(2, 1, 1, 'Kondwani Ng''ambi', 1, 'Buds',       '0977 100 001'),
(3, 6, 2, 'Mwila Mwansa',   4, 'Explorers',  '0966 200 001'),
(4, 6, 2, 'Natasha Mwansa', 2, 'Seedlings',  '0966 200 001'),
(5, 7, 1, 'Bupe Tembo',     3, 'Sunflowers', '0955 300 001'),
(6, 7, 2, 'Lubuto Tembo',   2, 'Seedlings',  '0955 300 001'),
(7, 1, 2, 'Thandiwe Ng''ambi', 4, 'Explorers', '0977 100 001'),
(8, 6, 1, 'Mutinta Mwansa', 1, 'Buds',       '0966 200 001');

INSERT INTO health_profiles (child_id, allergies, condition_notes, nurse_notes) VALUES
(1, 'egg',          'Mild asthma, uses inhaler when wheezing', 'Watch for wheezing after outdoor play.'),
(2, NULL,           NULL, NULL),
(3, 'peanut',       NULL, NULL),
(4, NULL,           'Recovering from a cold', 'Keep warm, offer plenty of water.'),
(5, 'milk',         NULL, NULL),
(6, NULL,           NULL, NULL),
(7, NULL,           NULL, NULL),
(8, NULL,           NULL, NULL);

-- Chipo's medicine was left at home, so the centre supplies it (extra fee) and the nurse has confirmed.
-- time_due is 10:00. Use the "simulate time" box on the nurse dashboard to make it due during the demo.
INSERT INTO medications (id, child_id, name, dosage, time_due, instructions, left_at_home, centre_supplied, fee, nurse_status) VALUES
(1, 1, 'Salbutamol inhaler', '2 puffs', '10:00:00', 'Give after the morning snack. Use the spacer.', 1, 1, 50.00, 'confirmed'),
(2, 4, 'Paracetamol syrup',  '5 ml',    '12:30:00', 'Only after lunch. Parent brought the bottle.', 0, 0, 0.00, 'not_needed');

INSERT INTO authorised_pickups (child_id, name, relationship, phone, id_number) VALUES
(1, 'Mary Ng''ambi',   'Mother', '0977 100 001', '123456/10/1'),
(1, 'Joseph Ng''ambi', 'Uncle',  '0977 100 010', '234567/10/1'),
(2, 'Mary Ng''ambi',   'Mother', '0977 100 001', '123456/10/1'),
(3, 'Joyce Mwansa',    'Mother', '0966 200 001', '345678/10/1'),
(4, 'Joyce Mwansa',    'Mother', '0966 200 001', '345678/10/1'),
(5, 'Daniel Tembo',    'Father', '0955 300 001', '456789/10/1'),
(6, 'Daniel Tembo',    'Father', '0955 300 001', '456789/10/1'),
(7, 'Mary Ng''ambi',   'Mother', '0977 100 001', '123456/10/1'),
(8, 'Joyce Mwansa',    'Mother', '0966 200 001', '345678/10/1');

-- Menu: day_of_week 0 means "served every day", so the demo works on any date.
INSERT INTO menu_items (day_of_week, meal_type, name, ingredients) VALUES
(0, 'breakfast', 'Maize porridge with sugar',        'maize meal, milk, sugar'),
(0, 'lunch',     'Nshima, egg relish and rape',      'maize meal, egg, tomato, onion, rape, cooking oil'),
(0, 'snack',     'Banana and water',                 'banana');

-- Reports and the parent's payment history look alive from the start.
INSERT INTO payments (parent_id, child_id, amount, description, reference, status, paid_at, created_at) VALUES
(1, 1, 1000.00, 'Full Day package: October 2026',  'LH-20261001-A1B2', 'paid', '2026-10-01 08:10:00', '2026-10-01 08:00:00'),
(6, 3, 1000.00, 'Full Day package: October 2026',  'LH-20261002-C3D4', 'paid', '2026-10-02 07:55:00', '2026-10-02 07:50:00'),
(7, 5,  600.00, 'Half Day package: October 2026',  'LH-20261003-E5F6', 'paid', '2026-10-03 09:00:00', '2026-10-03 08:45:00'),
(1, 1, 1050.00, 'Full Day package + medicine fee: November 2026', 'LH-20261101-PEND', 'pending', NULL, '2026-11-01 08:00:00');

-- A few earlier days of attendance so the admin report has something to show.
INSERT INTO attendance (child_id, att_date, check_in, check_out, dropped_by, picked_by, checked_in_by, checked_out_by) VALUES
(1, '2026-11-16', '2026-11-16 07:40:00', '2026-11-16 16:50:00', 'Mary Ng''ambi', 'Mary Ng''ambi', 'Mr Peter Zulu', 'Mr Peter Zulu'),
(3, '2026-11-16', '2026-11-16 07:45:00', '2026-11-16 17:05:00', 'Joyce Mwansa', 'Joyce Mwansa', 'Mr Peter Zulu', 'Mr Peter Zulu'),
(5, '2026-11-16', '2026-11-16 08:00:00', '2026-11-16 12:35:00', 'Daniel Tembo', 'Daniel Tembo', 'Mr Peter Zulu', 'Mr Peter Zulu'),
(1, '2026-11-13', '2026-11-13 07:35:00', '2026-11-13 16:45:00', 'Mary Ng''ambi', 'Joseph Ng''ambi', 'Mr Peter Zulu', 'Mr Peter Zulu');

INSERT INTO activity_log (user_id, user_name, role, action, created_at) VALUES
(5, 'Admin Mutale', 'admin', 'System set up with demo data', NOW());
