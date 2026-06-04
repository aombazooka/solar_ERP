<?php
/**
 * config.example.php — แม่แบบการตั้งค่า
 * ───────────────────────────────────────────────────────────
 * วิธีใช้: คัดลอกไฟล์นี้เป็น config.php แล้วแก้ค่าให้ตรงกับเครื่อง
 *   copy config\config.example.php config\config.php   (Windows)
 *
 * ⚠️ config.php อยู่ใน .gitignore — ห้าม commit รหัสจริงขึ้น git
 */

return [
    // ─── ฐานข้อมูล (XAMPP/MariaDB) ───
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => '3306',
        'name'    => 'solarsell',
        // ⚠️ XAMPP default = root / รหัสว่าง — ควรสร้าง user เฉพาะแอป (ดู db/schema.sql)
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // ─── แอป ───
    'app' => [
        'name'      => 'SolarSell',
        'base_url'  => 'auto',              // 'auto' = ตรวจโฟลเดอร์อัตโนมัติ (clone ชื่ออะไรก็ได้) · หรือระบุเอง เช่น '/tps_erp'
        'env'       => 'local',             // local | production
        'debug'     => true,                // production ให้ตั้ง false
        'timezone'  => 'Asia/Bangkok',
    ],

    // ─── ความปลอดภัย ───
    'security' => [
        'session_name'   => 'SOLARSELL_SESS',
        'session_lifetime' => 7200,         // วินาที (2 ชม.)
    ],
];
