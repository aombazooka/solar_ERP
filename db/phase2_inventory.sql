-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase 2: Inventory (stock movement)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phase2_inventory.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── stock_movements (ทุกการเคลื่อนไหวสต็อก — ledger) ──────
--  qty: + = เข้า, − = ออก   |  balance_after = ยอดคงเหลือหลังรายการ
CREATE TABLE IF NOT EXISTS stock_movements (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id    INT UNSIGNED NOT NULL,
    type          ENUM('receipt','issue','adjust','sale','return') NOT NULL,
    qty           INT          NOT NULL,          -- signed
    balance_after INT          NOT NULL,
    unit_cost     DECIMAL(12,2) NULL,             -- ต้นทุนต่อหน่วย (ตอนรับเข้า)
    ref_type      VARCHAR(40)  NULL,              -- vendor / sales_order / manual
    ref_id        VARCHAR(40)  NULL,
    note          VARCHAR(255) NULL,
    created_by    INT UNSIGNED NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id, id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- บันทึกยอดตั้งต้น (opening balance) ของสินค้าที่มีอยู่ ให้ ledger ตรงกับ stock_qty ปัจจุบัน
INSERT INTO stock_movements (product_id, type, qty, balance_after, ref_type, note, created_by, created_at)
SELECT id, 'adjust', stock_qty, stock_qty, 'manual', 'ยอดยกมา (opening balance)', 1, NOW()
FROM products
WHERE category <> 'service' AND stock_qty <> 0
  AND NOT EXISTS (SELECT 1 FROM stock_movements sm WHERE sm.product_id = products.id);
