-- ═══════════════════════════════════════════════════════════
--  SolarSell — Database Schema (Phase 0: Foundation)
--  MariaDB / InnoDB / utf8mb4
-- ═══════════════════════════════════════════════════════════
--  วิธีติดตั้ง:
--    1. เปิด phpMyAdmin → สร้าง database `solarsell` (utf8mb4_unicode_ci)
--    2. เลือก database แล้ว Import ไฟล์นี้
--    หรือ command line:
--      C:\xampp\mysql\bin\mysql -u root solarsell < db\schema.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── roles (ตำแหน่ง/บทบาท) ───────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(50)  NOT NULL UNIQUE,   -- admin, sales, installer, finance
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── permissions (สิทธิ์ย่อย) ─────────────────────────────
CREATE TABLE IF NOT EXISTS permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(80)  NOT NULL UNIQUE,   -- เช่น sales.view, sales.create
    name        VARCHAR(120) NOT NULL,
    module      VARCHAR(50)  NOT NULL           -- sales, install, inventory, finance
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── role_permissions (เชื่อม role ↔ permission) ──────────
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── users (ผู้ใช้ระบบ) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id       INT UNSIGNED NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP    NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── audit_log (บันทึกการกระทำ — รองรับ act_on_behalf) ────
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,              -- ผู้ที่ข้อมูลเป็นของเขา (เจ้าของ)
    created_by  INT UNSIGNED NULL,              -- ผู้กดบันทึกจริง (ตัวเอง/แอดมิน)
    action      VARCHAR(80)  NOT NULL,          -- login, create, update, delete
    entity      VARCHAR(80)  NULL,              -- ตาราง/โมดูลที่ถูกกระทำ
    entity_id   VARCHAR(64)  NULL,
    detail      TEXT         NULL,              -- JSON รายละเอียดเพิ่ม
    ip_address  VARCHAR(45)  NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── customers (ทะเบียนลูกค้า — Phase 1 เริ่มต้น) ──────────
CREATE TABLE IF NOT EXISTS customers (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(20)  NOT NULL UNIQUE,  -- CUS-0001
    name         VARCHAR(190) NOT NULL,
    type         ENUM('individual','company') NOT NULL DEFAULT 'individual',
    tax_id       VARCHAR(20)  NULL,
    phone        VARCHAR(30)  NULL,
    email        VARCHAR(190) NULL,
    address      VARCHAR(500) NULL,
    province     VARCHAR(80)  NULL,
    note         TEXT         NULL,
    created_by   INT UNSIGNED NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
