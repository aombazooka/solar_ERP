-- ═══════════════════════════════════════════════════════════
--  SolarSell — Group B2: สมุดรายวันทั่วไป (General Journal, double-entry)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseB2_journal.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS journal_entries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no      VARCHAR(30)  NOT NULL UNIQUE,    -- JV-2569-0001
    entry_date  DATE         NOT NULL,
    description VARCHAR(255) NOT NULL,
    total       DECIMAL(14,2) NOT NULL DEFAULT 0,  -- ยอดเดบิต(=เครดิต)
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_lines (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id    INT UNSIGNED NOT NULL,
    account_code VARCHAR(10) NOT NULL,
    debit       DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit      DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
