-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase 5b: ลงเวลา / ลา (รองรับ HR ทำแทน) + Payroll
--    อ้างอิง context.md §5.1 (Act on Behalf)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phase5b_attendance.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── attendance (ลงเวลา) ───────────────────────────────────
--  §5.1: แยก "เจ้าของข้อมูล" (employee_id) ออกจาก "ผู้บันทึก" (created_by)
CREATE TABLE IF NOT EXISTS attendance (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id     INT UNSIGNED NOT NULL,            -- เจ้าของข้อมูลจริง
    work_date       DATE         NOT NULL,
    check_in        TIME         NULL,
    check_out       TIME         NULL,
    note            VARCHAR(255) NULL,
    -- ── audit (act on behalf) ──
    created_by      INT UNSIGNED NULL,                -- ผู้กดบันทึก (user)
    entry_method    ENUM('self','on_behalf','import') NOT NULL DEFAULT 'self',
    acted_for_reason VARCHAR(255) NULL,               -- บังคับเมื่อ on_behalf
    is_locked       TINYINT(1)   NOT NULL DEFAULT 0,  -- ล็อกเมื่อปิดงวด payroll
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_emp_date (employee_id, work_date),
    INDEX idx_date (work_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── leave_requests (การลา) ────────────────────────────────
CREATE TABLE IF NOT EXISTS leave_requests (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id     INT UNSIGNED NOT NULL,
    leave_type      ENUM('sick','personal','vacation','other') NOT NULL DEFAULT 'personal',
    date_from       DATE         NOT NULL,
    date_to         DATE         NOT NULL,
    days            DECIMAL(4,1) NOT NULL DEFAULT 1,
    reason          VARCHAR(255) NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by     INT UNSIGNED NULL,               -- อนุมัติชั้นสอง
    -- ── audit (act on behalf) ──
    created_by      INT UNSIGNED NULL,
    entry_method    ENUM('self','on_behalf','import') NOT NULL DEFAULT 'self',
    acted_for_reason VARCHAR(255) NULL,
    is_locked       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── notifications (แจ้งพนักงานเมื่อ HR ทำแทน — §5.1 โปร่งใส) ──
CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    message     VARCHAR(500) NOT NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emp (employee_id, is_read),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── payroll_periods (งวดเงินเดือน) ───────────────────────
CREATE TABLE IF NOT EXISTS payroll_periods (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period      CHAR(7)      NOT NULL UNIQUE,         -- 2569-06
    status      ENUM('open','locked') NOT NULL DEFAULT 'open',
    locked_at   TIMESTAMP    NULL,
    locked_by   INT UNSIGNED NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── payroll_items (รายการเงินเดือนต่อพนักงานต่องวด) ──────
CREATE TABLE IF NOT EXISTS payroll_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id    INT UNSIGNED NOT NULL,
    employee_id  INT UNSIGNED NOT NULL,
    base_salary  DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission   DECIMAL(12,2) NOT NULL DEFAULT 0,    -- ดึงจากยอดขาย
    leave_days   DECIMAL(4,1)  NOT NULL DEFAULT 0,
    deduction    DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_pay      DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_period_emp (period_id, employee_id),
    FOREIGN KEY (period_id)   REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มฟิลด์เงินเดือนฐาน + อัตราคอมมิชชั่น ให้ employees (idempotent)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='base_salary');
SET @sql := IF(@col=0, 'ALTER TABLE employees ADD COLUMN base_salary DECIMAL(12,2) NOT NULL DEFAULT 0, ADD COLUMN commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── permissions เพิ่ม ───
INSERT INTO permissions (slug, name, module) VALUES
    ('hr.payroll', 'จัดการเงินเดือน', 'hr'),
    ('hr.approve', 'อนุมัติการลา',    'hr')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ─── ผูกพนักงานกับบัญชี login + ตั้งเงินเดือน/คอมฯ ───
UPDATE employees SET user_id=(SELECT id FROM users WHERE email='admin@solarsell.local'),
       base_salary=45000, commission_rate=2.0 WHERE code='EMP-0001';
UPDATE employees SET user_id=(SELECT id FROM users WHERE email='sales@solarsell.local'),
       base_salary=25000, commission_rate=3.0 WHERE code='EMP-0002';
UPDATE employees SET base_salary=18000, commission_rate=0 WHERE code='EMP-0003';
