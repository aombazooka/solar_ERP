<?php
/**
 * bootstrap.php — จุดเริ่มต้นที่ทุกหน้าต้อง require ก่อนเสมอ
 * โหลด config → ตั้งค่า error/timezone → เริ่ม session → โหลด helper หลัก
 */

declare(strict_types=1);

// ─── โหลด config ───
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    exit('ยังไม่มี config/config.php — คัดลอกจาก config.example.php ก่อน');
}
$config = require $configFile;

// ─── error reporting ตาม env ───
if (!empty($config['app']['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');

// ─── timezone ───
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

// ─── session (ตั้งค่าให้ปลอดภัย) ───
if (session_status() === PHP_SESSION_NONE) {
    session_name($config['security']['session_name'] ?? 'SOLARSELL_SESS');
    session_set_cookie_params([
        'lifetime' => $config['security']['session_lifetime'] ?? 7200,
        'path'     => '/',
        'httponly' => true,                       // กัน JS อ่าน cookie (XSS)
        'samesite' => 'Lax',                      // กัน CSRF ข้ามเว็บ
        'secure'   => !empty($_SERVER['HTTPS']),  // production (https) จะส่งเฉพาะ https
    ]);
    session_start();
}

// ─── ทำ config ให้เข้าถึงได้ทั่วระบบ ───
$GLOBALS['config'] = $config;

// ─── โหลดไฟล์หลัก ───
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Stock.php';
require_once __DIR__ . '/Sales.php';
require_once __DIR__ . '/Finance.php';
require_once __DIR__ . '/Geo.php';
require_once __DIR__ . '/Hr.php';
require_once __DIR__ . '/Purchasing.php';
