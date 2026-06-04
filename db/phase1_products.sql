-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase 1: Vendors + Products
--  รันหลัง schema.sql / seed.sql
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phase1_products.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── vendors (ซัพพลายเออร์) ────────────────────────────────
CREATE TABLE IF NOT EXISTS vendors (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20)  NOT NULL UNIQUE,   -- VEN-0001
    name        VARCHAR(190) NOT NULL,
    contact     VARCHAR(120) NULL,              -- ผู้ติดต่อ
    phone       VARCHAR(30)  NULL,
    email       VARCHAR(190) NULL,
    tax_id      VARCHAR(20)  NULL,
    address     VARCHAR(500) NULL,
    note        TEXT         NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── products (สินค้า / วัสดุ) ─────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku           VARCHAR(40)  NOT NULL UNIQUE,  -- รหัสสินค้า เช่น PNL-550-JK
    name          VARCHAR(190) NOT NULL,
    category      ENUM('panel','inverter','battery','mounting','accessory','service')
                  NOT NULL DEFAULT 'accessory',
    brand         VARCHAR(80)  NULL,
    unit          VARCHAR(20)  NOT NULL DEFAULT 'ชิ้น',  -- ชิ้น/ชุด/เมตร/งาน
    power_w       INT UNSIGNED NULL,             -- กำลังไฟ (วัตต์) สำหรับ panel/inverter
    cost_price    DECIMAL(12,2) NOT NULL DEFAULT 0,  -- ราคาทุน
    sell_price    DECIMAL(12,2) NOT NULL DEFAULT 0,  -- ราคาขาย
    stock_qty     INT          NOT NULL DEFAULT 0,    -- คงเหลือ (Phase 2 จะคุมด้วย stock movement)
    reorder_level INT          NOT NULL DEFAULT 0,    -- จุดสั่งซื้อซ้ำ
    vendor_id     INT UNSIGNED NULL,
    spec          VARCHAR(500) NULL,             -- สเปคย่อ
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_by    INT UNSIGNED NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cat (category),
    INDEX idx_name (name),
    FOREIGN KEY (vendor_id)  REFERENCES vendors(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── ข้อมูลตัวอย่าง: vendors ───────────────────────────────
INSERT INTO vendors (code, name, contact, phone, created_by) VALUES
    ('VEN-0001', 'บจก. โซลาร์ ซัพพลาย', 'คุณวิชัย', '02-111-2222', 1),
    ('VEN-0002', 'Jinko Solar (ตัวแทน)', 'คุณนภา',  '02-333-4444', 1),
    ('VEN-0003', 'บจก. อินเวอร์เตอร์ไทย', 'คุณสมพร', '038-555-666', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── ข้อมูลตัวอย่าง: products (สินค้าโซลาร์จริง) ────────────
INSERT INTO products (sku, name, category, brand, unit, power_w, cost_price, sell_price, stock_qty, reorder_level, vendor_id, spec, created_by) VALUES
    ('PNL-550-JK', 'แผงโซลาร์ Mono 550W', 'panel', 'Jinko', 'แผง', 550, 3200, 4500, 120, 30,
        (SELECT id FROM vendors WHERE code='VEN-0002'), 'Monocrystalline N-Type, 144 cells', 1),
    ('PNL-450-LR', 'แผงโซลาร์ Mono 450W', 'panel', 'Longi', 'แผง', 450, 2700, 3800, 80, 20,
        (SELECT id FROM vendors WHERE code='VEN-0001'), 'Monocrystalline PERC', 1),
    ('INV-5K-HY',  'อินเวอร์เตอร์ Hybrid 5kW', 'inverter', 'Growatt', 'เครื่อง', 5000, 28000, 38000, 25, 5,
        (SELECT id FROM vendors WHERE code='VEN-0003'), 'Hybrid 1-phase, MPPT x2', 1),
    ('INV-10K-OG', 'อินเวอร์เตอร์ On-Grid 10kW', 'inverter', 'Huawei', 'เครื่อง', 10000, 45000, 62000, 12, 3,
        (SELECT id FROM vendors WHERE code='VEN-0003'), 'On-Grid 3-phase', 1),
    ('BAT-5K-LF',  'แบตเตอรี่ LiFePO4 5kWh', 'battery', 'Pylontech', 'ลูก', NULL, 42000, 58000, 18, 4,
        (SELECT id FROM vendors WHERE code='VEN-0001'), 'LiFePO4 48V 100Ah', 1),
    ('MNT-RAIL-4M','รางยึดแผงอลูมิเนียม 4เมตร', 'mounting', 'Local', 'เส้น', NULL, 420, 650, 300, 50,
        (SELECT id FROM vendors WHERE code='VEN-0001'), 'Anodized aluminium', 1),
    ('ACC-DCCB',   'DC Circuit Breaker 1000V', 'accessory', 'Schneider', 'ตัว', NULL, 350, 600, 200, 40,
        (SELECT id FROM vendors WHERE code='VEN-0001'), '2P 25A', 1),
    ('SRV-INSTALL','ค่าบริการติดตั้ง (ต่อ kWp)', 'service', NULL, 'kWp', NULL, 0, 3500, 0, 0,
        NULL, 'ค่าแรงติดตั้งมาตรฐาน', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
