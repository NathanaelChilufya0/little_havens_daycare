<?php
// Little Haven: database connection and basic config.
// Change these if your XAMPP/WAMP MySQL uses a different user or password.

const DB_HOST = '127.0.0.1';
const DB_NAME = 'little_havens_daycare';
const DB_USER = 'root';
const DB_PASS = '';

// The folder name under htdocs (XAMPP) or www (WAMP). Used to build links.
// If you put the project in htdocs/little_havens_daycare, leave this as it is.
const BASE_URL = '/little_havens_daycare';

date_default_timezone_set('Africa/Lusaka');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die('<h2>Database connection failed</h2>'
              . '<p>Start MySQL in XAMPP/WAMP, then import <code>database.sql</code> in phpMyAdmin.</p>'
              . '<p>Check the settings at the top of <code>db.php</code>.</p>');
        }
    }
    return $pdo;
}

/** Run a prepared statement and return all rows. */
function q(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Run a prepared statement and return the first row (or null). */
function q1(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Run an INSERT/UPDATE/DELETE and return the number of affected rows. */
function run(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Run an INSERT and return the new row id. */
function insert(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) db()->lastInsertId();
}
