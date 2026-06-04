<?php
/**
 * Database.php — เชื่อมต่อ MariaDB ด้วย PDO (singleton)
 * ───────────────────────────────────────────────────────────
 * ทุก query ใช้ prepared statement → กัน SQL Injection อัตโนมัติ
 */

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    /** คืน PDO instance เดียวตลอดทั้ง request */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = $GLOBALS['config']['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,  // ใช้ prepared จริงของ DB
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            $debug = $GLOBALS['config']['app']['debug'] ?? false;
            exit($debug
                ? 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . htmlspecialchars($e->getMessage())
                : 'เชื่อมต่อฐานข้อมูลไม่ได้ กรุณาตรวจสอบว่าเปิด MySQL ใน XAMPP แล้ว');
        }

        return self::$pdo;
    }

    /** query ที่คืนหลายแถว */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** query ที่คืนแถวเดียว (หรือ null) */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** query ที่คืนค่าเดียว (scalar) */
    public static function scalar(string $sql, array $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /** INSERT/UPDATE/DELETE → คืนจำนวนแถวที่กระทบ */
    public static function run(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** id ของแถวที่เพิ่ง insert */
    public static function lastId(): string
    {
        return self::pdo()->lastInsertId();
    }
}
