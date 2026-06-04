-- ═══════════════════════════════════════════════════════════
--  SolarSell — OT (ขอทำงานล่วงเวลา) + ช่อง ot_pay ใน payroll
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseD_ot.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS ot_requests (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id     INT UNSIGNED NOT NULL,
    ot_date         DATE         NOT NULL,
    hours           DECIMAL(4,1) NOT NULL,
    reason          VARCHAR(255) NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by     INT UNSIGNED NULL,
    -- audit (act on behalf §5.1)
    created_by      INT UNSIGNED NULL,
    entry_method    ENUM('self','on_behalf','import') NOT NULL DEFAULT 'self',
    acted_for_reason VARCHAR(255) NULL,
    is_locked       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emp (employee_id, ot_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- เพิ่มช่อง ot_pay ใน payroll_items (idempotent)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payroll_items' AND COLUMN_NAME='ot_pay');
SET @sql := IF(@col=0, 'ALTER TABLE payroll_items ADD COLUMN ot_pay DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER commission', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- สิทธิ์ OT (ใช้ hr.approve ร่วมกับการลา)
INSERT INTO permissions (slug, name, module) VALUES ('hr.ot', 'อนุมัติ OT', 'hr')
ON DUPLICATE KEY UPDATE name=VALUES(name);
