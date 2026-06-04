-- ═══════════════════════════════════════════════════════════
--  SolarSell — Group B1: รับเข้าสินค้า (Goods Receipt) + เจ้าหนี้ (AP)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseB1_purchasing.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── goods_receipts (ใบรับเข้าสินค้า : header) ─────────────
CREATE TABLE IF NOT EXISTS goods_receipts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no      VARCHAR(30)  NOT NULL UNIQUE,     -- GR-2569-0001
    vendor_id   INT UNSIGNED NOT NULL,
    status      ENUM('received','cancelled') NOT NULL DEFAULT 'received',
    subtotal    DECIMAL(14,2) NOT NULL DEFAULT 0,
    vat         DECIMAL(14,2) NOT NULL DEFAULT 0,
    total       DECIMAL(14,2) NOT NULL DEFAULT 0,
    note        VARCHAR(500) NULL,
    received_at DATE         NOT NULL,
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id)  REFERENCES vendors(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gr_id       INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    qty         DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_cost   DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total  DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (gr_id)      REFERENCES goods_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── vendor_bills (เจ้าหนี้การค้า : AP) ────────────────────
CREATE TABLE IF NOT EXISTS vendor_bills (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no      VARCHAR(30)  NOT NULL UNIQUE,     -- AP-2569-0001
    vendor_id   INT UNSIGNED NOT NULL,
    gr_id       INT UNSIGNED NULL,
    status      ENUM('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
    total       DECIMAL(14,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    issued_at   DATE         NOT NULL,
    due_date    DATE         NULL,
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id)  REFERENCES vendors(id),
    FOREIGN KEY (gr_id)      REFERENCES goods_receipts(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── vendor_payments (จ่ายเจ้าหนี้) ────────────────────────
CREATE TABLE IF NOT EXISTS vendor_payments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no      VARCHAR(30)  NOT NULL UNIQUE,     -- VP-2569-0001
    bill_id     INT UNSIGNED NOT NULL,
    vendor_id   INT UNSIGNED NOT NULL,
    amount      DECIMAL(14,2) NOT NULL,
    method      ENUM('cash','transfer','cheque','card') NOT NULL DEFAULT 'transfer',
    paid_at     DATE         NOT NULL,
    note        VARCHAR(255) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id)    REFERENCES vendor_bills(id),
    FOREIGN KEY (vendor_id)  REFERENCES vendors(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
