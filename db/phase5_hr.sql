-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase 5: HR (employees, work sites, geofencing check-in)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phase5_hr.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── employees (ทะเบียนพนักงาน) ────────────────────────────
CREATE TABLE IF NOT EXISTS employees (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(20)  NOT NULL UNIQUE,    -- EMP-0001
    user_id      INT UNSIGNED NULL,               -- เชื่อมบัญชี login (ถ้ามี)
    name         VARCHAR(120) NOT NULL,
    position     VARCHAR(80)  NULL,
    department   VARCHAR(80)  NULL,
    phone        VARCHAR(30)  NULL,
    team         VARCHAR(40)  NULL,               -- ทีมช่าง: A/B/C
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── work_sites (จุดหน้างานติดตั้ง) ────────────────────────
CREATE TABLE IF NOT EXISTS work_sites (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_name       VARCHAR(190) NOT NULL,
    latitude        DECIMAL(10,7) NOT NULL,
    longitude       DECIMAL(10,7) NOT NULL,
    allowed_radius_m INT          NOT NULL DEFAULT 150,
    assigned_team   VARCHAR(40)  NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── site_checkins (เช็คอินหน้างาน — ข้อมูล PDPA) ──────────
--  ⚠️ distance_from_site_m คำนวณที่ SERVER เท่านั้น (กันปลอม)
CREATE TABLE IF NOT EXISTS site_checkins (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id         INT UNSIGNED NOT NULL,
    site_id             INT UNSIGNED NOT NULL,
    checkin_lat         DECIMAL(10,7) NOT NULL,   -- พิกัดดิบจาก client
    checkin_long        DECIMAL(10,7) NOT NULL,
    distance_from_site_m DECIMAL(10,2) NOT NULL,  -- server คำนวณ (Haversine)
    gps_accuracy_m      DECIMAL(8,2) NULL,
    photo_path          VARCHAR(255) NULL,        -- เก็บนอก public + จำกัดสิทธิ์
    status              ENUM('approved','out_of_range','pending_review') NOT NULL,
    device_info         VARCHAR(255) NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emp (employee_id, created_at),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id)     REFERENCES work_sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── permissions HR ───
INSERT INTO permissions (slug, name, module) VALUES
    ('hr.view',   'ดูข้อมูล HR',     'hr'),
    ('hr.manage', 'จัดการข้อมูล HR', 'hr'),
    ('hr.checkin','เช็คอินหน้างาน',  'hr')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- installer + admin ได้สิทธิ์ HR (admin bypass อยู่แล้ว แต่ใส่ให้ชัด)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='installer' AND p.slug IN ('hr.view','hr.checkin')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- ─── ข้อมูลตัวอย่าง ───
INSERT INTO employees (code, name, position, department, team, phone) VALUES
    ('EMP-0001', 'สมชาย พลังแสง', 'ผู้จัดการขาย',   'ขาย',     NULL, '081-000-0001'),
    ('EMP-0002', 'ช่างเอก ติดตั้งดี', 'หัวหน้าช่าง',   'ติดตั้ง', 'A',  '081-000-0002'),
    ('EMP-0003', 'ช่างโท สายไฟ',    'ช่างไฟฟ้า',      'ติดตั้ง', 'A',  '081-000-0003')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- work site ตัวอย่าง (พิกัดอนุสาวรีย์ชัยฯ กรุงเทพ)
INSERT INTO work_sites (site_name, latitude, longitude, allowed_radius_m, assigned_team) VALUES
    ('ไซต์ติดตั้ง โรงแรมซีไซด์ พัทยา', 12.9236000, 100.8824000, 200, 'A'),
    ('ไซต์ติดตั้ง โรงงาน XYZ ระยอง',   12.6814000, 101.2570000, 300, 'A')
ON DUPLICATE KEY UPDATE site_name=VALUES(site_name);
