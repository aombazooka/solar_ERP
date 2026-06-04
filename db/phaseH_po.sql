-- ═══════════════════════════════════════════════════════════
--  SolarSell — ใบสั่งซื้อ (Purchase Order) → รับเข้า
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseH_po.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no        VARCHAR(30)  NOT NULL UNIQUE,     -- PO-2569-0001
    vendor_id     INT UNSIGNED NOT NULL,
    status        ENUM('open','partial','received','cancelled') NOT NULL DEFAULT 'open',
    subtotal      DECIMAL(14,2) NOT NULL DEFAULT 0,
    vat           DECIMAL(14,2) NOT NULL DEFAULT 0,
    total         DECIMAL(14,2) NOT NULL DEFAULT 0,
    expected_date DATE         NULL,
    note          VARCHAR(500) NULL,
    created_by    INT UNSIGNED NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id)  REFERENCES vendors(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id        INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NULL,
    description  VARCHAR(255) NOT NULL,
    qty          INT          NOT NULL DEFAULT 1,
    qty_received INT          NOT NULL DEFAULT 0,
    unit_cost    DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total   DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (po_id)      REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- เชื่อมใบรับเข้ากับ PO (idempotent)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='goods_receipts' AND COLUMN_NAME='po_id');
SET @sql := IF(@col=0, 'ALTER TABLE goods_receipts ADD COLUMN po_id INT UNSIGNED NULL AFTER vendor_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
