-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase D: ข้อมูลพื้นฐาน HR
--    ตำแหน่งงาน / แผนก / ทีม / ประเภทวันลา (กำหนดโควต้าได้)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseD_master_data.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── positions (ตำแหน่งงาน) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS positions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── departments (แผนก / ฝ่าย) ──────────────────────────────
CREATE TABLE IF NOT EXISTS departments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── teams (ทีมงาน) ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS teams (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(20)  NOT NULL UNIQUE,
    name       VARCHAR(100) NOT NULL,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── leave_types (ประเภทวันลา + โควต้า) ─────────────────────
--  quota_days = 0 → ไม่จำกัดวัน
--  deduct_pay = 1 → หักเงินเดือนเมื่อลา
CREATE TABLE IF NOT EXISTS leave_types (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30)  NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    quota_days  SMALLINT     NOT NULL DEFAULT 0,
    deduct_pay  TINYINT(1)   NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── seed ประเภทลาเริ่มต้น (ตรงกับ ENUM เดิม) ───────────────
INSERT INTO leave_types (code, name, quota_days, deduct_pay, sort_order) VALUES
    ('sick',     'ลาป่วย',    30, 0, 1),
    ('personal', 'ลากิจ',      3, 1, 2),
    ('vacation', 'ลาพักร้อน',  6, 0, 3),
    ('other',    'อื่นๆ',      0, 1, 4)
ON DUPLICATE KEY UPDATE name=VALUES(name), quota_days=VALUES(quota_days), deduct_pay=VALUES(deduct_pay);

-- ─── เปลี่ยน leave_type จาก ENUM → VARCHAR (idempotent) ─────
--  ทำให้รองรับประเภทลาที่กำหนดเองได้ไม่จำกัด
SET @ct := (
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'leave_requests'
      AND COLUMN_NAME = 'leave_type'
);
SET @sql := IF(
    LEFT(@ct, 4) = 'enum',
    "ALTER TABLE leave_requests MODIFY COLUMN leave_type VARCHAR(30) NOT NULL DEFAULT 'personal'",
    'SELECT 1'
);
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- ─── seed ตำแหน่ง / แผนก / ทีม ──────────────────────────────
INSERT IGNORE INTO positions (name, sort_order) VALUES
    ('ผู้จัดการทั่วไป',  1),
    ('ผู้จัดการขาย',    2),
    ('หัวหน้าช่าง',     3),
    ('ช่างไฟฟ้า',      4),
    ('พนักงานขาย',     5),
    ('พนักงานบัญชี',   6),
    ('พนักงานคลัง',    7);

INSERT IGNORE INTO departments (name, sort_order) VALUES
    ('บริหาร',    1),
    ('ขาย',       2),
    ('ติดตั้ง',   3),
    ('บัญชี',     4),
    ('คลังสินค้า',5);

INSERT IGNORE INTO teams (code, name, sort_order) VALUES
    ('A', 'ทีม A', 1),
    ('B', 'ทีม B', 2),
    ('C', 'ทีม C', 3);

-- ─── permission ──────────────────────────────────────────────
INSERT INTO permissions (slug, name, module) VALUES
    ('hr.settings', 'ตั้งค่าข้อมูลพื้นฐาน HR', 'hr')
ON DUPLICATE KEY UPDATE name = VALUES(name);

SET FOREIGN_KEY_CHECKS = 1;
