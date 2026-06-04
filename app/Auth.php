<?php
/**
 * Auth.php — ระบบ login / session / RBAC
 * ───────────────────────────────────────────────────────────
 * RBAC: ผู้ใช้ 1 คนมี 1 role, แต่ละ role มีหลาย permission
 * เช็คสิทธิ์ด้วย Auth::can('sales.create')
 */

declare(strict_types=1);

final class Auth
{
    /** พยายาม login ด้วย username+password → true ถ้าสำเร็จ */
    public static function attempt(string $username, string $password): bool
    {
        $user = Database::one(
            'SELECT id, name, username, password_hash, role_id, is_active
             FROM users WHERE username = :u LIMIT 1',
            ['u' => $username]
        );

        if (!$user || !$user['is_active']) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // กัน session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']  = (int) $user['id'];
        $_SESSION['role_id']  = (int) $user['role_id'];
        $_SESSION['name']     = $user['name'];
        $_SESSION['logged_at'] = time();

        Database::run(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id',
            ['id' => $user['id']]
        );

        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** สร้าง username จากชื่ออังกฤษ (ไม่ซ้ำ) เช่น "John Smith" → "john.smith" */
    public static function generateUsername(string $nameEn): string
    {
        $base = strtolower(trim($nameEn));
        $base = preg_replace('/[^a-z0-9]+/', '.', $base);
        $base = trim($base, '.');
        if ($base === '') $base = 'user';
        $u = $base; $i = 1;
        while (Database::scalar('SELECT id FROM users WHERE username = :u', ['u' => $u])) {
            $i++; $u = $base . $i;
        }
        return $u;
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /** ข้อมูลผู้ใช้ปัจจุบัน (พร้อม role) — cache ใน static */
    public static function user(): ?array
    {
        static $cached = null;
        if (!self::check()) {
            return null;
        }
        if ($cached === null) {
            $cached = Database::one(
                'SELECT u.id, u.name, u.username, u.email, u.role_id, r.name AS role_name, r.slug AS role_slug
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE u.id = :id',
                ['id' => self::id()]
            );
        }
        return $cached;
    }

    /** รายการ permission slug ของผู้ใช้ปัจจุบัน */
    public static function permissions(): array
    {
        static $perms = null;
        if (!self::check()) {
            return [];
        }
        if ($perms === null) {
            $rows = Database::all(
                'SELECT p.slug
                 FROM role_permissions rp
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.role_id = :rid',
                ['rid' => $_SESSION['role_id']]
            );
            $perms = array_column($rows, 'slug');
        }
        return $perms;
    }

    /** เช็คว่ามีสิทธิ์นี้ไหม — admin (role slug=admin) ผ่านทุกอย่าง */
    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user && $user['role_slug'] === 'admin') {
            return true;
        }
        return in_array($permission, self::permissions(), true);
    }

    /** บังคับว่าต้อง login — ไม่งั้นเด้งไป login */
    public static function require(): void
    {
        if (!self::check()) {
            flash('error', 'กรุณาเข้าสู่ระบบก่อน');
            redirect('login.php');
        }
    }

    /** บังคับสิทธิ์ — ไม่มีสิทธิ์ = 403 */
    public static function requireCan(string $permission): void
    {
        self::require();
        if (!self::can($permission)) {
            http_response_code(403);
            exit('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (' . e($permission) . ')');
        }
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && $u['role_slug'] === 'admin';
    }

    /** บังคับว่าต้องเป็น admin */
    public static function requireAdmin(): void
    {
        self::require();
        if (!self::isAdmin()) {
            http_response_code(403);
            exit('เฉพาะผู้ดูแลระบบเท่านั้น');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
