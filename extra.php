<?php
// Little Haven: extra helpers for the admin, parent and nurse modules.
// Save in the project ROOT, next to auth.php. New pages start with:
//   require_once __DIR__ . '/../extra.php';

require_once __DIR__ . '/auth.php';

// Extra fee (kwacha) when the centre supplies a child's medicine. Read from the settings table; 50 if missing.
define('CENTRE_MEDICINE_FEE', (float) (q1("SELECT v FROM settings WHERE k = 'medicine_fee'")['v'] ?? 50));

/** Column names of a table (cached). */
function table_cols(string $table): array
{
    if (!isset($GLOBALS['lh_cols'][$table])) {
        if (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $cols = array_column(q('PRAGMA table_info(' . $table . ')'), 'name');
        } else {
            $cols = array_column(
                q('SELECT COLUMN_NAME AS col FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]),
                'col'
            );
        }
        $GLOBALS['lh_cols'][$table] = $cols;
    }
    return $GLOBALS['lh_cols'][$table];
}

function has_col(string $table, string $col): bool
{
    return in_array($col, table_cols($table), true);
}

/** Returns the first candidate column that exists. If none exists, adds the first one and returns it. */
function col_or_add(string $table, array $candidates, string $definition): string
{
    foreach ($candidates as $c) {
        if (has_col($table, $c)) {
            return $c;
        }
    }
    run("ALTER TABLE `$table` ADD COLUMN `{$candidates[0]}` $definition");
    unset($GLOBALS['lh_cols'][$table]);
    return $candidates[0];
}

/** INSERT that silently skips keys which are not real columns. Returns the new id. */
function insert_row(string $table, array $data): int
{
    $data = array_intersect_key($data, array_flip(table_cols($table)));
    if (!$data) {
        throw new InvalidArgumentException("Nothing to insert into $table");
    }
    $names = implode(',', array_map(fn($c) => "`$c`", array_keys($data)));
    $marks = implode(',', array_fill(0, count($data), '?'));
    return insert("INSERT INTO `$table` ($names) VALUES ($marks)", array_values($data));
}

/** UPDATE that silently skips keys which are not real columns. Returns rows changed. */
function update_row(string $table, array $data, string $where, array $params = []): int
{
    $data = array_intersect_key($data, array_flip(table_cols($table)));
    if (!$data) {
        return 0;
    }
    $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
    return run("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $params));
}

/** First value of the first row (for COUNT / SUM queries). */
function scalar(string $sql, array $params = [])
{
    $row = q1($sql, $params);
    return $row ? array_values($row)[0] : 0;
}

/** Trimmed POST field. */
function posted(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** "none", "nil", "n/a" and empty text all mean there is nothing to record. */
function is_none(?string $s): bool
{
    return in_array(strtolower(trim((string) $s)), ['', 'none', 'no', 'nil', 'n/a', 'na', '-'], true);
}

/** Makes sure the payments table has the columns the payment screens use; returns the real column names. */
function payment_cols(): array
{
    return [
        'desc'    => col_or_add('payments', ['description', 'note', 'details'], 'VARCHAR(200) NULL'),
        'method'  => col_or_add('payments', ['method', 'payment_method'], 'VARCHAR(80) NULL'),
        'ref'     => col_or_add('payments', ['reference', 'ref', 'txn_ref'], 'VARCHAR(40) NULL'),
        'paid_at' => col_or_add('payments', ['paid_at', 'paid_on'], 'DATETIME NULL'),
        'created' => col_or_add('payments', ['created_at'], 'DATETIME NULL'),
    ];
}