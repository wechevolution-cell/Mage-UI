<?php
declare(strict_types=1);

/**
 * Two kinds of SQLite database:
 *  - data/platform.sqlite : control plane (super admins, shops, plans, subscriptions, audit…)
 *  - data/shops/shop_{id}.sqlite : one isolated file per tenant (products, orders, customers…)
 * A tenant connection is only ever opened from the shop id resolved server-side
 * (domain mapping or a verified impersonation token), never from user input.
 */
final class DB
{
    private static ?PDO $platform = null;
    /** @var array<int,PDO> */
    private static array $shops = [];

    public static function platformPath(): string { return DATA_DIR . '/platform.sqlite'; }
    public static function shopPath(int $id): string { return DATA_DIR . '/shops/shop_' . $id . '.sqlite'; }

    public static function platform(): PDO
    {
        if (!self::$platform) {
            self::$platform = self::open(self::platformPath());
            Migrator::run(self::$platform, require ROOT . '/app/migrations/platform.php');
        }
        return self::$platform;
    }

    public static function shop(int $id): PDO
    {
        if ($id < 1) throw new RuntimeException('Invalid shop id');
        if (!isset(self::$shops[$id])) {
            ensure_dir(DATA_DIR . '/shops');
            $pdo = self::open(self::shopPath($id));
            Migrator::run($pdo, require ROOT . '/app/migrations/shop.php');
            self::$shops[$id] = $pdo;
        }
        return self::$shops[$id];
    }

    public static function closeShop(int $id): void { unset(self::$shops[$id]); }
    public static function closeAll(): void { self::$platform = null; self::$shops = []; }

    private static function open(string $path): PDO
    {
        ensure_dir(dirname($path));
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 10000');
        $pdo->exec('PRAGMA temp_store = MEMORY');
        if (function_exists('str_fold')) {
            $pdo->sqliteCreateFunction('FOLD', fn($s) => $s === null ? null : str_fold((string) $s), 1, PDO::SQLITE_DETERMINISTIC);
        }
        return $pdo;
    }
}

final class Migrator
{
    /** @param array<int,string|callable> $migrations ordered list; index+1 = version */
    public static function run(PDO $pdo, array $migrations): void
    {
        $v = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        $target = count($migrations);
        if ($v >= $target) return;
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $v = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
            for ($i = $v; $i < $target; $i++) {
                $m = $migrations[$i];
                is_callable($m) ? $m($pdo) : $pdo->exec($m);
                $pdo->exec('PRAGMA user_version = ' . ($i + 1));
            }
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }
}

/* ---------- query helpers ---------- */
function db_exec(PDO $db, string $sql, array $p = []): PDOStatement
{
    $st = $db->prepare($sql);
    $st->execute($p);
    return $st;
}
function db_one(PDO $db, string $sql, array $p = []): ?array { $r = db_exec($db, $sql, $p)->fetch(); return $r ?: null; }
function db_all(PDO $db, string $sql, array $p = []): array { return db_exec($db, $sql, $p)->fetchAll(); }
function db_val(PDO $db, string $sql, array $p = []): mixed { $v = db_exec($db, $sql, $p)->fetchColumn(); return $v === false ? null : $v; }
function db_col(PDO $db, string $sql, array $p = []): array { return db_exec($db, $sql, $p)->fetchAll(PDO::FETCH_COLUMN); }
function db_insert(PDO $db, string $table, array $data): int
{
    $cols = array_keys($data);
    foreach ($cols as $c) { if (!preg_match('/^[a-z_][a-z0-9_]*$/', $c)) throw new InvalidArgumentException('bad column'); }
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    db_exec($db, $sql, array_values($data));
    return (int) $db->lastInsertId();
}
function db_update(PDO $db, string $table, array $data, string $where, array $params = []): int
{
    $sets = [];
    foreach (array_keys($data) as $c) {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $c)) throw new InvalidArgumentException('bad column');
        $sets[] = "$c = ?";
    }
    return db_exec($db, "UPDATE $table SET " . implode(', ', $sets) . " WHERE $where", array_merge(array_values($data), $params))->rowCount();
}
function db_tx(PDO $db, callable $fn): mixed
{
    if ($db->inTransaction()) return $fn($db);
    $db->exec('BEGIN IMMEDIATE');
    try {
        $r = $fn($db);
        $db->exec('COMMIT');
        return $r;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}
function in_placeholders(array $ids): string { return implode(',', array_fill(0, max(1, count($ids)), '?')); }

/** key/value settings stored in a `settings` table (JSON values) */
final class SettingsCache { public static array $c = []; }
function setting_get(PDO $db, string $key, mixed $default = null): mixed
{
    $ck = spl_object_id($db) . '|' . $key;
    if (!array_key_exists($ck, SettingsCache::$c)) {
        SettingsCache::$c[$ck] = db_val($db, 'SELECT value FROM settings WHERE key = ?', [$key]);
    }
    $v = SettingsCache::$c[$ck];
    if ($v === null) return $default;
    $d = json_decode((string) $v, true);
    return json_last_error() === JSON_ERROR_NONE ? $d : $v;
}
function setting_set(PDO $db, string $key, mixed $value): void
{
    db_exec($db, 'INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, je($value)]);
    unset(SettingsCache::$c[spl_object_id($db) . '|' . $key]);
}
function settings_many(PDO $db, array $defaults): array
{
    $out = [];
    foreach ($defaults as $k => $d) { $out[$k] = setting_get($db, $k, $d); }
    return $out;
}
