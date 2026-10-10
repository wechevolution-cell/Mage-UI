<?php
declare(strict_types=1);

/**
 * Generic credential auth with lockout (5 failures => 15 minutes) for any scope:
 *   'sa' super admin (platform DB), 'admin' shop staff (tenant DB), 'customer' storefront customers.
 */
final class Auth
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_SECONDS = 900;

    public static function lockKey(string $scope, string $email): string { return $scope . '|' . mb_strtolower(trim($email)); }

    public static function isLocked(PDO $db, string $key): int
    {
        $r = db_one($db, 'SELECT locked_until FROM login_attempts WHERE key = ?', [$key]);
        $left = $r ? (int) $r['locked_until'] - time() : 0;
        return max(0, $left);
    }

    public static function registerFailure(PDO $db, string $key): void
    {
        $r = db_one($db, 'SELECT attempts, locked_until FROM login_attempts WHERE key = ?', [$key]);
        $attempts = $r && (int) $r['locked_until'] < time() ? (int) $r['attempts'] + 1 : ($r ? (int) $r['attempts'] + 1 : 1);
        $locked = 0;
        if ($attempts >= self::MAX_ATTEMPTS) { $locked = time() + self::LOCK_SECONDS; $attempts = 0; }
        db_exec($db, 'INSERT OR REPLACE INTO login_attempts(key, attempts, locked_until, updated_at) VALUES(?,?,?,?)', [$key, $attempts, $locked, time()]);
    }

    public static function clearFailures(PDO $db, string $key): void { db_exec($db, 'DELETE FROM login_attempts WHERE key = ?', [$key]); }

    /**
     * @return array{ok:bool, user?:array, error?:string}
     */
    public static function attempt(PDO $db, string $scope, string $table, string $email, string $password): array
    {
        $email = trim($email);
        $key = self::lockKey($scope, $email);
        $ipKey = $scope . '|ip|' . client_ip();
        $left = max(self::isLocked($db, $key), self::isLocked($db, $ipKey));
        if ($left > 0) {
            return ['ok' => false, 'error' => 'Πολλές αποτυχημένες προσπάθειες. Δοκιμάστε ξανά σε ' . ceil($left / 60) . ' λεπτά.'];
        }
        $u = db_one($db, "SELECT * FROM $table WHERE email = ? AND deleted_at IS NULL", [$email]);
        $valid = $u && !empty($u['password_hash']) && password_verify($password, $u['password_hash']);
        if (!$valid) {
            if (!$u) { password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); }
            self::registerFailure($db, $key);
            // softer per-IP limiter (4x) against credential stuffing across many emails
            $r = db_one($db, 'SELECT attempts FROM login_attempts WHERE key = ?', [$ipKey]);
            if (!$r || (int) $r['attempts'] < self::MAX_ATTEMPTS * 4 - 1) {
                db_exec($db, 'INSERT INTO login_attempts(key, attempts, locked_until, updated_at) VALUES(?,1,0,?) ON CONFLICT(key) DO UPDATE SET attempts = attempts + 1, updated_at = excluded.updated_at', [$ipKey, time()]);
            } else {
                db_exec($db, 'UPDATE login_attempts SET attempts = 0, locked_until = ? WHERE key = ?', [time() + self::LOCK_SECONDS, $ipKey]);
            }
            return ['ok' => false, 'error' => 'Λάθος email ή κωδικός.'];
        }
        if (($u['status'] ?? 'active') !== 'active') {
            return ['ok' => false, 'error' => 'Ο λογαριασμός είναι απενεργοποιημένος.'];
        }
        self::clearFailures($db, $key);
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            db_exec($db, "UPDATE $table SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        return ['ok' => true, 'user' => $u];
    }

    public static function login(string $scope, array $user, array $extra = []): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'][$scope] = ['id' => (int) $user['id'], 'fp' => substr(hash('sha256', (string) $user['password_hash']), 0, 16), 'at' => time()] + $extra;
        unset($_SESSION['_csrf']);
    }

    public static function logout(string $scope): void
    {
        unset($_SESSION['auth'][$scope]);
        session_regenerate_id(true);
    }

    public static function session(string $scope): ?array { return $_SESSION['auth'][$scope] ?? null; }

    /** Loads the logged-in row and validates the password fingerprint (logout on password change). */
    public static function user(PDO $db, string $scope, string $table): ?array
    {
        $s = self::session($scope);
        if (!$s) return null;
        $u = db_one($db, "SELECT * FROM $table WHERE id = ? AND deleted_at IS NULL", [$s['id']]);
        if (!$u || ($u['status'] ?? 'active') !== 'active' || substr(hash('sha256', (string) $u['password_hash']), 0, 16) !== $s['fp']) {
            unset($_SESSION['auth'][$scope]);
            return null;
        }
        return $u;
    }

    public static function validatePassword(string $p): ?string
    {
        if (mb_strlen($p) < 10) return 'Ο κωδικός πρέπει να έχει τουλάχιστον 10 χαρακτήρες.';
        if (!preg_match('/\p{L}/u', $p) || !preg_match('/\d/', $p)) return 'Ο κωδικός πρέπει να περιέχει γράμματα και αριθμούς.';
        return null;
    }
}
