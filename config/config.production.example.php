<?php
/**
 * config.production.example.php — แม่แบบสำหรับโฮสติ้งจริง (เช่น InfinityFree)
 * ───────────────────────────────────────────────────────────
 * วิธีใช้:
 *   1) คัดลอกไฟล์นี้เป็น config/config.php (อัปโหลดเฉพาะ config.php ขึ้นโฮสต์)
 *   2) แก้ค่า db ให้ตรงกับที่ระบบโฮสต์ออกให้ (vPanel → MySQL Databases)
 *   3) ตั้ง base_url = '' ถ้าวางไฟล์ไว้ที่ "รากของโดเมน" (htdocs/)
 *
 * ⚠️ config.php อยู่ใน .gitignore — ห้าม commit รหัสจริงขึ้น git
 */

return [
    // ─── ฐานข้อมูล MySQL ของโฮสต์ ───
    // InfinityFree: ดูค่าได้ที่ vPanel → "MySQL Databases"
    //   host = เช่น sqlXXX.infinityfree.com (อย่าใช้ localhost)
    //   name/user = ขึ้นต้นด้วย if0_xxxxxxxx_
    'db' => [
        'host'    => 'sqlXXX.infinityfree.com',     // ← MySQL Hostname จากแผงควบคุม
        'port'    => '3306',
        'name'    => 'if0_42095305_solarsell',       // ← ชื่อฐานข้อมูลที่สร้างไว้
        'user'    => 'if0_42095305',                 // ← MySQL Username
        'pass'    => 'ใส่รหัสผ่านฐานข้อมูลที่นี่',     // ← รหัสที่ตั้งไว้ตอนสร้าง DB
        'charset' => 'utf8mb4',
    ],

    // ─── แอป ───
    'app' => [
        'name'      => 'SolarSell',
        'base_url'  => '',              // '' = วางที่รากโดเมน (เช่น https://tps.freedev.app/)
        'env'       => 'production',
        'debug'     => false,           // production ต้อง false เสมอ (ไม่โชว์ error)
        'timezone'  => 'Asia/Bangkok',
    ],

    // ─── ความปลอดภัย ───
    'security' => [
        'session_name'     => 'SOLARSELL_SESS',
        'session_lifetime' => 7200,     // 2 ชั่วโมง
    ],
];
