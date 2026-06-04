-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase 4: Finance (invoices + payments / AR)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phase4_finance.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── invoices (ใบแจ้งหนี้ / ใบกำกับภาษี) ───────────────────
CREATE TABLE IF NOT EXISTS invoices (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no       VARCHAR(30)  NOT NULL UNIQUE,    -- INV-2569-0001
    order_id     INT UNSIGNED NULL,
    customer_id  INT UNSIGNED NOT NULL,
    status       ENUM('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
    total        DECIMAL(14,2) NOT NULL DEFAULT 0,
    paid_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
    issued_at    DATE         NOT NULL,
    due_date     DATE         NULL,
    note         VARCHAR(500) NULL,
    created_by   INT UNSIGNED NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)    REFERENCES sales_orders(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── invoice_items (สำเนารายการเพื่อพิมพ์บิล) ─────────────
CREATE TABLE IF NOT EXISTS invoice_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id  INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    qty         DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total  DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── payments (การรับชำระ) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no      VARCHAR(30)  NOT NULL UNIQUE,     -- PAY-2569-0001
    invoice_id  INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    amount      DECIMAL(14,2) NOT NULL,
    method      ENUM('cash','transfer','cheque','card') NOT NULL DEFAULT 'transfer',
    paid_at     DATE         NOT NULL,
    note        VARCHAR(255) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id)  REFERENCES invoices(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
