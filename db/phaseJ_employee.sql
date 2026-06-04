-- ═══════════════════════════════════════════════════════════
--  SolarSell — ขยายข้อมูลพนักงาน + เบิกเงินล่วงหน้า (Salary Advance)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseJ_employee.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ─── ฟิลด์เพิ่มของพนักงาน (base_salary/commission_rate มีแล้ว) ───
ALTER TABLE employees
  ADD COLUMN IF NOT EXISTS national_id  VARCHAR(20)  NULL AFTER name,
  ADD COLUMN IF NOT EXISTS birth_date   DATE         NULL,
  ADD COLUMN IF NOT EXISTS hire_date    DATE         NULL,
  ADD COLUMN IF NOT EXISTS email        VARCHAR(190) NULL,
  ADD COLUMN IF NOT EXISTS address      VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS education    VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS bank_name    VARCHAR(80)  NULL,
  ADD COLUMN IF NOT EXISTS bank_account VARCHAR(40)  NULL,
  ADD COLUMN IF NOT EXISTS bank_branch  VARCHAR(80)  NULL;

-- ─── เบิกเงินล่วงหน้า ───
CREATE TABLE IF NOT EXISTS salary_advances (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no          VARCHAR(30)  NOT NULL UNIQUE,    -- ADV-2569-0001
    employee_id     INT UNSIGNED NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    reason          VARCHAR(255) NULL,
    status          ENUM('pending','approved','rejected','deducted') NOT NULL DEFAULT 'pending',
    request_date    DATE         NOT NULL,
    approved_by     INT UNSIGNED NULL,
    period_deducted CHAR(7)      NULL,              -- งวดที่หักคืน เช่น 2569-06
    created_by      INT UNSIGNED NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emp (employee_id, status),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ช่องหักเบิกล่วงหน้าใน payroll_items ───
ALTER TABLE payroll_items
  ADD COLUMN IF NOT EXISTS advance_deduct DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER deduction;

-- ─── สิทธิ์ ───
INSERT INTO permissions (slug, name, module) VALUES ('hr.advance', 'จัดการเบิกเงินล่วงหน้า', 'hr')
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.slug IN ('admin','hr') AND p.slug='hr.advance'
ON DUPLICATE KEY UPDATE role_id = role_id;
