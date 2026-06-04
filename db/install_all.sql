-- ============================================================
-- SolarSell ERP — Combined installer (schema + seed + migrations)
-- สร้างอัตโนมัติ — สำหรับ import ครั้งเดียวผ่าน phpMyAdmin (เช่น InfinityFree)
-- วิธีใช้: เลือกฐานข้อมูลของคุณใน phpMyAdmin -> แท็บ Import -> เลือกไฟล์นี้
-- ห้ามมี CREATE DATABASE/USE — รันในฐานข้อมูลที่เลือกไว้เท่านั้น
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;


-- ════════════════════════════════════════════════════════
-- >>> schema.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> seed.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — ข้อมูลเริ่มต้น (Seed)
--  รันหลัง schema.sql
-- ═══════════════════════════════════════════════════════════
--  ⚠️ รหัสผ่านเริ่มต้นของทุก user = "password"
--     hash นี้สร้างจาก password_hash('password', PASSWORD_DEFAULT)
--     เปลี่ยนรหัสทันทีหลัง login ครั้งแรก
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─── roles ───
INSERT INTO roles (slug, name, description) VALUES
    ('admin',     'ผู้ดูแลระบบ',     'เข้าถึงได้ทุกส่วน'),
    ('sales',     'ฝ่ายขาย',         'จัดการลูกค้า ใบเสนอราคา ใบสั่งซื้อ'),
    ('installer', 'หัวหน้าช่างติดตั้ง', 'จัดการงานติดตั้งและทีมช่าง'),
    ('finance',   'ฝ่ายการเงิน',     'ออกบิล รับชำระ รายงานการเงิน')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── permissions ───
INSERT INTO permissions (slug, name, module) VALUES
    ('dashboard.view',   'ดูแดชบอร์ด',          'dashboard'),
    ('sales.view',       'ดูงานขาย',            'sales'),
    ('sales.create',     'สร้างงานขาย',         'sales'),
    ('sales.edit',       'แก้ไขงานขาย',         'sales'),
    ('install.view',     'ดูงานติดตั้ง',        'install'),
    ('install.manage',   'จัดการงานติดตั้ง',     'install'),
    ('inventory.view',   'ดูคลังสินค้า',        'inventory'),
    ('inventory.manage', 'จัดการคลังสินค้า',     'inventory'),
    ('finance.view',     'ดูการเงิน',           'finance'),
    ('finance.manage',   'จัดการการเงิน',        'finance'),
    ('customer.view',    'ดูทะเบียนลูกค้า',      'customer'),
    ('customer.manage',  'จัดการทะเบียนลูกค้า',  'customer')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── role_permissions ───
-- sales: ดูแดชบอร์ด + งานขาย + ลูกค้า + ดูงานติดตั้ง/คลัง
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'sales'
  AND p.slug IN ('dashboard.view','sales.view','sales.create','sales.edit',
                 'customer.view','customer.manage','install.view',
                 'inventory.view','inventory.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- installer: ดูแดชบอร์ด + จัดการงานติดตั้ง + ดูคลัง
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'installer'
  AND p.slug IN ('dashboard.view','install.view','install.manage',
                 'inventory.view','customer.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- finance: ดูแดชบอร์ด + จัดการการเงิน + ดูงานขาย
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'finance'
  AND p.slug IN ('dashboard.view','finance.view','finance.manage',
                 'sales.view','customer.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ─── users (รหัส = "password") ───
INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'สมชาย พลังแสง', 'admin@solarsell.local',
       '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse',
       r.id, 1
FROM roles r WHERE r.slug = 'admin'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'สมหญิง ขายดี', 'sales@solarsell.local',
       '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse',
       r.id, 1
FROM roles r WHERE r.slug = 'sales'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── ลูกค้าตัวอย่าง ───
INSERT INTO customers (code, name, type, phone, province, created_by) VALUES
    ('CUS-0001', 'โรงงานอุตสาหกรรม XYZ', 'company',    '038-111-222', 'ระยอง',    1),
    ('CUS-0002', 'นายสมศักดิ์ ใจดี',      'individual', '081-234-5678', 'กรุงเทพฯ', 1),
    ('CUS-0003', 'โรงแรมซีไซด์ พัทยา',    'company',    '038-999-888', 'ชลบุรี',   1)
ON DUPLICATE KEY UPDATE name = VALUES(name);


-- ════════════════════════════════════════════════════════
-- >>> phase1_products.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> phase1b_coa.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase 1 (เพิ่ม): ผังบัญชี (Chart of Accounts)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phase1b_coa.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS chart_of_accounts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(10)  NOT NULL UNIQUE,        -- เลขที่บัญชี เช่น 1100
    name        VARCHAR(150) NOT NULL,
    type        ENUM('asset','liability','equity','revenue','expense') NOT NULL,
    parent_code VARCHAR(10)  NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ผังบัญชีมาตรฐานย่อ (เหมาะธุรกิจขาย/ติดตั้ง)
INSERT INTO chart_of_accounts (code, name, type, parent_code) VALUES
    ('1000','สินทรัพย์',              'asset',     NULL),
    ('1100','เงินสดและเงินฝากธนาคาร', 'asset',     '1000'),
    ('1200','ลูกหนี้การค้า (AR)',      'asset',     '1000'),
    ('1300','สินค้าคงเหลือ',          'asset',     '1000'),
    ('2000','หนี้สิน',                'liability', NULL),
    ('2100','เจ้าหนี้การค้า (AP)',     'liability', '2000'),
    ('2200','ภาษีขายค้างจ่าย (VAT)',  'liability', '2000'),
    ('3000','ส่วนของเจ้าของ',         'equity',    NULL),
    ('4000','รายได้',                 'revenue',   NULL),
    ('4100','รายได้จากการขายอุปกรณ์', 'revenue',   '4000'),
    ('4200','รายได้ค่าบริการติดตั้ง',  'revenue',   '4000'),
    ('5000','ค่าใช้จ่าย',             'expense',   NULL),
    ('5100','ต้นทุนสินค้าขาย (COGS)', 'expense',   '5000'),
    ('5200','เงินเดือนและค่าแรง',     'expense',   '5000'),
    ('5300','คอมมิชชั่นการขาย',       'expense',   '5000')
ON DUPLICATE KEY UPDATE name = VALUES(name);


-- ════════════════════════════════════════════════════════
-- >>> phase2_inventory.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> phase3_sales.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase 3: Sales (quotation → order → delivery)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phase3_sales.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── quotations (ใบเสนอราคา : header) ──────────────────────
CREATE TABLE IF NOT EXISTS quotations (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no       VARCHAR(30)  NOT NULL UNIQUE,    -- QT-2569-0001
    customer_id  INT UNSIGNED NOT NULL,
    status       ENUM('draft','sent','accepted','rejected','converted') NOT NULL DEFAULT 'draft',
    system_type  ENUM('on_grid','hybrid','off_grid') NULL,
    capacity_kwp DECIMAL(10,2) NULL,
    subtotal     DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount     DECIMAL(14,2) NOT NULL DEFAULT 0,
    vat          DECIMAL(14,2) NOT NULL DEFAULT 0,
    total        DECIMAL(14,2) NOT NULL DEFAULT 0,
    valid_until  DATE         NULL,
    note         VARCHAR(500) NULL,
    created_by   INT UNSIGNED NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── quotation_items (รายการในใบเสนอราคา) ─────────────────
CREATE TABLE IF NOT EXISTS quotation_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_id  INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED NULL,             -- NULL = บรรทัดอิสระ (free text)
    description   VARCHAR(255) NOT NULL,
    qty           DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total    DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)   REFERENCES products(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── sales_orders (ใบสั่งขาย : header) ─────────────────────
CREATE TABLE IF NOT EXISTS sales_orders (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no       VARCHAR(30)  NOT NULL UNIQUE,    -- SO-2569-0001
    quotation_id INT UNSIGNED NULL,
    customer_id  INT UNSIGNED NOT NULL,
    status       ENUM('pending','delivered','invoiced','cancelled') NOT NULL DEFAULT 'pending',
    total        DECIMAL(14,2) NOT NULL DEFAULT 0,
    delivered_at TIMESTAMP    NULL,
    note         VARCHAR(500) NULL,
    created_by   INT UNSIGNED NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id),
    FOREIGN KEY (customer_id)  REFERENCES customers(id),
    FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── sales_order_items ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS sales_order_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id      INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED NULL,
    description   VARCHAR(255) NOT NULL,
    qty           DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total    DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (order_id)   REFERENCES sales_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- ════════════════════════════════════════════════════════
-- >>> phase4_finance.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> phase5_hr.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase 5: HR (employees, work sites, geofencing check-in)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phase5_hr.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── employees (ทะเบียนพนักงาน) ────────────────────────────
CREATE TABLE IF NOT EXISTS employees (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(20)  NOT NULL UNIQUE,    -- EMP-0001
    user_id      INT UNSIGNED NULL,               -- เชื่อมบัญชี login (ถ้ามี)
    name         VARCHAR(120) NOT NULL,
    position     VARCHAR(80)  NULL,
    department   VARCHAR(80)  NULL,
    phone        VARCHAR(30)  NULL,
    team         VARCHAR(40)  NULL,               -- ทีมช่าง: A/B/C
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── work_sites (จุดหน้างานติดตั้ง) ────────────────────────
CREATE TABLE IF NOT EXISTS work_sites (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_name       VARCHAR(190) NOT NULL,
    latitude        DECIMAL(10,7) NOT NULL,
    longitude       DECIMAL(10,7) NOT NULL,
    allowed_radius_m INT          NOT NULL DEFAULT 150,
    assigned_team   VARCHAR(40)  NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── site_checkins (เช็คอินหน้างาน — ข้อมูล PDPA) ──────────
--  ⚠️ distance_from_site_m คำนวณที่ SERVER เท่านั้น (กันปลอม)
CREATE TABLE IF NOT EXISTS site_checkins (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id         INT UNSIGNED NOT NULL,
    site_id             INT UNSIGNED NOT NULL,
    checkin_lat         DECIMAL(10,7) NOT NULL,   -- พิกัดดิบจาก client
    checkin_long        DECIMAL(10,7) NOT NULL,
    distance_from_site_m DECIMAL(10,2) NOT NULL,  -- server คำนวณ (Haversine)
    gps_accuracy_m      DECIMAL(8,2) NULL,
    photo_path          VARCHAR(255) NULL,        -- เก็บนอก public + จำกัดสิทธิ์
    status              ENUM('approved','out_of_range','pending_review') NOT NULL,
    device_info         VARCHAR(255) NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emp (employee_id, created_at),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id)     REFERENCES work_sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── permissions HR ───
INSERT INTO permissions (slug, name, module) VALUES
    ('hr.view',   'ดูข้อมูล HR',     'hr'),
    ('hr.manage', 'จัดการข้อมูล HR', 'hr'),
    ('hr.checkin','เช็คอินหน้างาน',  'hr')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- installer + admin ได้สิทธิ์ HR (admin bypass อยู่แล้ว แต่ใส่ให้ชัด)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='installer' AND p.slug IN ('hr.view','hr.checkin')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- ─── ข้อมูลตัวอย่าง ───
INSERT INTO employees (code, name, position, department, team, phone) VALUES
    ('EMP-0001', 'สมชาย พลังแสง', 'ผู้จัดการขาย',   'ขาย',     NULL, '081-000-0001'),
    ('EMP-0002', 'ช่างเอก ติดตั้งดี', 'หัวหน้าช่าง',   'ติดตั้ง', 'A',  '081-000-0002'),
    ('EMP-0003', 'ช่างโท สายไฟ',    'ช่างไฟฟ้า',      'ติดตั้ง', 'A',  '081-000-0003')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- work site ตัวอย่าง (พิกัดอนุสาวรีย์ชัยฯ กรุงเทพ)
INSERT INTO work_sites (site_name, latitude, longitude, allowed_radius_m, assigned_team) VALUES
    ('ไซต์ติดตั้ง โรงแรมซีไซด์ พัทยา', 12.9236000, 100.8824000, 200, 'A'),
    ('ไซต์ติดตั้ง โรงงาน XYZ ระยอง',   12.6814000, 101.2570000, 300, 'A')
ON DUPLICATE KEY UPDATE site_name=VALUES(site_name);


-- ════════════════════════════════════════════════════════
-- >>> phase5b_attendance.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> phaseB1_purchasing.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> phaseB2_journal.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> phaseB3_crm.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — Group B3: Leads (CRM) + งานติดตั้ง (Installation jobs)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseB3_crm.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── leads (ลูกค้าสนใจ) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS leads (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(190) NOT NULL,
    phone        VARCHAR(30)  NULL,
    source       VARCHAR(60)  NULL,             -- Facebook/LINE/แนะนำ/Walk-in
    interest_system ENUM('on_grid','hybrid','off_grid') NULL,
    interest_kwp DECIMAL(10,2) NULL,
    est_value    DECIMAL(14,2) NULL,            -- มูลค่าคาดการณ์
    status       ENUM('new','contacted','quoted','won','lost') NOT NULL DEFAULT 'new',
    note         VARCHAR(500) NULL,
    converted_customer_id INT UNSIGNED NULL,
    assigned_to  INT UNSIGNED NULL,             -- user
    created_by   INT UNSIGNED NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (converted_customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── installation_jobs (งานติดตั้ง) ───────────────────────
CREATE TABLE IF NOT EXISTS installation_jobs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no        VARCHAR(30)  NOT NULL UNIQUE,  -- JOB-2569-0001
    order_id      INT UNSIGNED NULL,             -- จากใบสั่งขาย
    customer_id   INT UNSIGNED NOT NULL,
    site_id       INT UNSIGNED NULL,             -- จุดหน้างาน (geofencing)
    system_type   ENUM('on_grid','hybrid','off_grid') NULL,
    capacity_kwp  DECIMAL(10,2) NULL,
    team          VARCHAR(40)  NULL,
    scheduled_date DATE        NULL,
    status        ENUM('pending','in_progress','done','cancelled') NOT NULL DEFAULT 'pending',
    progress      TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- 0-100
    note          VARCHAR(500) NULL,
    created_by    INT UNSIGNED NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)    REFERENCES sales_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (site_id)     REFERENCES work_sites(id)   ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ตัวอย่าง leads
INSERT INTO leads (name, phone, source, interest_system, interest_kwp, est_value, status, created_by) VALUES
    ('บริษัท กรีนพาวเวอร์ จำกัด', '02-555-1234', 'Facebook', 'on_grid', 40, 1200000, 'new', 1),
    ('คุณประยงค์ มีสุข',          '081-222-3333', 'แนะนำ',    'hybrid',  8,  280000,  'contacted', 1),
    ('โรงเรียนชัยวิทยา',          '02-777-8888', 'LINE',     'on_grid', 20, 520000,  'quoted', 1);


-- ════════════════════════════════════════════════════════
-- >>> phaseC_quickclock.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — เพิ่ม kind (เข้า/ออก) ให้ site_checkins สำหรับปุ่มตอกบัตรลัด
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseC_quickclock.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_checkins' AND COLUMN_NAME='kind');
SET @sql := IF(@col=0,
  "ALTER TABLE site_checkins ADD COLUMN kind ENUM('in','out') NOT NULL DEFAULT 'in' AFTER site_id",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ════════════════════════════════════════════════════════
-- >>> phaseD_master_data.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase D: ข้อมูลพื้นฐาน HR
--    ตำแหน่งงาน / แผนก / ทีม / ประเภทวันลา (กำหนดโควต้าได้)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseD_master_data.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── positions (ตำแหน่งงาน) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS positions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── departments (แผนก / ฝ่าย) ──────────────────────────────
CREATE TABLE IF NOT EXISTS departments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── teams (ทีมงาน) ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS teams (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(20)  NOT NULL UNIQUE,
    name       VARCHAR(100) NOT NULL,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── leave_types (ประเภทวันลา + โควต้า) ─────────────────────
--  quota_days = 0 → ไม่จำกัดวัน
--  deduct_pay = 1 → หักเงินเดือนเมื่อลา
CREATE TABLE IF NOT EXISTS leave_types (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30)  NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    quota_days  SMALLINT     NOT NULL DEFAULT 0,
    deduct_pay  TINYINT(1)   NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── seed ประเภทลาเริ่มต้น (ตรงกับ ENUM เดิม) ───────────────
INSERT INTO leave_types (code, name, quota_days, deduct_pay, sort_order) VALUES
    ('sick',     'ลาป่วย',    30, 0, 1),
    ('personal', 'ลากิจ',      3, 1, 2),
    ('vacation', 'ลาพักร้อน',  6, 0, 3),
    ('other',    'อื่นๆ',      0, 1, 4)
ON DUPLICATE KEY UPDATE name=VALUES(name), quota_days=VALUES(quota_days), deduct_pay=VALUES(deduct_pay);

-- ─── เปลี่ยน leave_type จาก ENUM → VARCHAR (idempotent) ─────
--  ทำให้รองรับประเภทลาที่กำหนดเองได้ไม่จำกัด
SET @ct := (
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'leave_requests'
      AND COLUMN_NAME = 'leave_type'
);
SET @sql := IF(
    LEFT(@ct, 4) = 'enum',
    "ALTER TABLE leave_requests MODIFY COLUMN leave_type VARCHAR(30) NOT NULL DEFAULT 'personal'",
    'SELECT 1'
);
PREPARE _s FROM @sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- ─── seed ตำแหน่ง / แผนก / ทีม ──────────────────────────────
INSERT IGNORE INTO positions (name, sort_order) VALUES
    ('ผู้จัดการทั่วไป',  1),
    ('ผู้จัดการขาย',    2),
    ('หัวหน้าช่าง',     3),
    ('ช่างไฟฟ้า',      4),
    ('พนักงานขาย',     5),
    ('พนักงานบัญชี',   6),
    ('พนักงานคลัง',    7);

INSERT IGNORE INTO departments (name, sort_order) VALUES
    ('บริหาร',    1),
    ('ขาย',       2),
    ('ติดตั้ง',   3),
    ('บัญชี',     4),
    ('คลังสินค้า',5);

INSERT IGNORE INTO teams (code, name, sort_order) VALUES
    ('A', 'ทีม A', 1),
    ('B', 'ทีม B', 2),
    ('C', 'ทีม C', 3);

-- ─── permission ──────────────────────────────────────────────
INSERT INTO permissions (slug, name, module) VALUES
    ('hr.settings', 'ตั้งค่าข้อมูลพื้นฐาน HR', 'hr')
ON DUPLICATE KEY UPDATE name = VALUES(name);

SET FOREIGN_KEY_CHECKS = 1;


-- ════════════════════════════════════════════════════════
-- >>> phaseD_ot.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> phaseE_cancel.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — เพิ่มสถานะ "ยกเลิก" (cancelled) ให้คำขอลา/OT
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseE_cancel.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

ALTER TABLE leave_requests
  MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending';

ALTER TABLE ot_requests
  MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending';


-- ════════════════════════════════════════════════════════
-- >>> phaseF_hr_role.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — สร้าง role ทรัพยากรบุคคล (hr) + สิทธิ์ + user ตัวอย่าง
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseF_hr_role.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- 1) role
INSERT INTO roles (slug, name, description) VALUES
    ('hr', 'ทรัพยากรบุคคล', 'จัดการพนักงาน เวลา ลา OT เงินเดือน')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 2) ผูกสิทธิ์ HR เต็ม + ดูแดชบอร์ด
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'hr'
  AND p.slug IN ('dashboard.view',
                 'hr.view','hr.manage','hr.checkin','hr.payroll','hr.approve','hr.ot','hr.settings')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- 3) user HR (รหัสผ่าน = "password" — เปลี่ยนทันทีหลัง login)
INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'มานี ทรัพยากร', 'hr@solarsell.local',
       '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse',
       r.id, 1
FROM roles r WHERE r.slug = 'hr'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 4) ข้อมูลพนักงานของ HR + ผูกบัญชี (ให้มี personal dashboard ด้วย)
INSERT INTO employees (code, user_id, name, position, department, base_salary, commission_rate)
SELECT 'EMP-0004', u.id, 'มานี ทรัพยากร', 'เจ้าหน้าที่ทรัพยากรบุคคล', 'ทรัพยากรบุคคล', 28000, 0
FROM users u WHERE u.email = 'hr@solarsell.local'
ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), position = VALUES(position), department = VALUES(department);


-- ════════════════════════════════════════════════════════
-- >>> phaseG_company.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — ตั้งค่าบริษัท (สำหรับขึ้นบนเอกสาร) + สิทธิ์ตั้งค่า
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseG_company.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS company_profile (
    id          TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    name        VARCHAR(190) NOT NULL DEFAULT 'SolarSell',
    legal_name  VARCHAR(190) NULL,
    address     VARCHAR(500) NULL,
    phone       VARCHAR(60)  NULL,
    email       VARCHAR(190) NULL,
    tax_id      VARCHAR(30)  NULL,
    logo_emoji  VARCHAR(16)  NOT NULL DEFAULT '☀️',
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_single CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO company_profile (id, name, legal_name, address, phone, email, tax_id)
VALUES (1, 'SolarSell', 'บริษัท โซลาร์เซลล์ จำกัด',
        '123 ถนนพลังงาน แขวงแสงอาทิตย์ เขตเมือง กรุงเทพฯ 10000',
        '02-000-0000', 'contact@solarsell.local', '0105500000000')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO permissions (slug, name, module) VALUES ('settings.company', 'ตั้งค่าบริษัท', 'system')
ON DUPLICATE KEY UPDATE name = VALUES(name);


-- ════════════════════════════════════════════════════════
-- >>> phaseH_po.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> phaseI_wht.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — ภาษีหัก ณ ที่จ่าย (WHT) ในการจ่ายเจ้าหนี้
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseI_wht.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

SET @c1 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vendor_payments' AND COLUMN_NAME='wht_amount');
SET @s1 := IF(@c1=0,
  'ALTER TABLE vendor_payments
     ADD COLUMN wht_rate   DECIMAL(5,2)  NOT NULL DEFAULT 0 AFTER amount,
     ADD COLUMN wht_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER wht_rate',
  'SELECT 1');
PREPARE s FROM @s1; EXECUTE s; DEALLOCATE PREPARE s;


-- ════════════════════════════════════════════════════════
-- >>> phaseJ_employee.sql
-- ════════════════════════════════════════════════════════
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


-- ════════════════════════════════════════════════════════
-- >>> phaseK_roles.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — เพิ่ม Role: Supervisor / Executive / Staff
--    + สิทธิ์อนุมัติเฉพาะทีม (hr.approve_team) ตาม context §5.1
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseK_roles.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ─── permission ใหม่ ───
INSERT INTO permissions (slug, name, module) VALUES
    ('hr.approve_team', 'อนุมัติลา/OT เฉพาะทีม', 'hr')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── roles ใหม่ ───
INSERT INTO roles (slug, name, description) VALUES
    ('supervisor', 'หัวหน้างาน',   'อนุมัติลา/OT ของทีมตัวเอง + self-service'),
    ('executive',  'ผู้บริหาร',     'ดูข้อมูล/รายงานทุกฝ่าย (อ่านอย่างเดียว)'),
    ('staff',      'พนักงานทั่วไป', 'self-service: ตอกบัตร/ลา/OT/สลิป')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── grant สิทธิ์ ───
-- supervisor: self-service + ดูข้อมูล HR + อนุมัติเฉพาะทีม
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='supervisor' AND p.slug IN ('dashboard.view','hr.checkin','hr.view','hr.approve_team')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- executive: ดูอย่างเดียวทุกฝ่าย (เฉพาะ *.view ไม่มี manage/create)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='executive' AND p.slug IN
  ('dashboard.view','sales.view','install.view','inventory.view','finance.view','customer.view','hr.view')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- staff: self-service ล้วน
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='staff' AND p.slug IN ('dashboard.view','hr.checkin')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- HR ได้ hr.approve_team ด้วย (เข้ากล่องรออนุมัติได้ — เห็นทั้งองค์กรเพราะมี hr.approve อยู่แล้ว)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='hr' AND p.slug='hr.approve_team'
ON DUPLICATE KEY UPDATE role_id=role_id;

-- ─── user ตัวอย่าง (รหัส = "password") ───
INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'วิชัย หัวหน้าทีม', 'supervisor@solarsell.local', '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse', r.id, 1
FROM roles r WHERE r.slug='supervisor' ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'ประธาน บริหาร', 'exec@solarsell.local', '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse', r.id, 1
FROM roles r WHERE r.slug='executive' ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'สมหมาย พนักงาน', 'staff@solarsell.local', '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse', r.id, 1
FROM roles r WHERE r.slug='staff' ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ─── employees + ผูกบัญชี (supervisor & staff อยู่ทีม A เพื่อทดสอบ) ───
INSERT INTO employees (code, user_id, name, position, department, team, base_salary)
SELECT 'EMP-0005', u.id, 'วิชัย หัวหน้าทีม', 'หัวหน้าทีมติดตั้ง', 'ติดตั้ง', 'A', 30000
FROM users u WHERE u.email='supervisor@solarsell.local'
ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), team=VALUES(team);

INSERT INTO employees (code, user_id, name, position, department, team, base_salary)
SELECT 'EMP-0006', u.id, 'สมหมาย พนักงาน', 'ช่างติดตั้ง', 'ติดตั้ง', 'A', 16000
FROM users u WHERE u.email='staff@solarsell.local'
ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), team=VALUES(team);


-- ════════════════════════════════════════════════════════
-- >>> phaseL_service.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — งานบริการหลังการขาย (O&M) + ทะเบียนอุปกรณ์/รับประกัน
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseL_service.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── ใบงานบริการ (Service Ticket) ──────────────────────────
CREATE TABLE IF NOT EXISTS service_tickets (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no         VARCHAR(30)  NOT NULL UNIQUE,    -- SRV-2569-0001
    customer_id    INT UNSIGNED NOT NULL,
    job_id         INT UNSIGNED NULL,               -- งานติดตั้งที่เกี่ยวข้อง
    ticket_type    ENUM('maintenance','repair','claim','inspection') NOT NULL DEFAULT 'repair',
    priority       ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status         ENUM('open','in_progress','resolved','closed','cancelled') NOT NULL DEFAULT 'open',
    title          VARCHAR(190) NOT NULL,
    description    TEXT         NULL,
    assigned_team  VARCHAR(40)  NULL,
    scheduled_date DATE         NULL,
    resolved_at    TIMESTAMP    NULL,
    resolution     VARCHAR(500) NULL,
    created_by     INT UNSIGNED NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status, priority),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (job_id)      REFERENCES installation_jobs(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ทะเบียนอุปกรณ์ที่ติดตั้ง + การรับประกัน ───────────────
CREATE TABLE IF NOT EXISTS installed_assets (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT UNSIGNED NOT NULL,
    job_id         INT UNSIGNED NULL,
    product_id     INT UNSIGNED NULL,
    category       ENUM('panel','inverter','battery','other') NOT NULL DEFAULT 'other',
    brand          VARCHAR(80)  NULL,
    serial_no      VARCHAR(100) NOT NULL,
    install_date   DATE         NULL,
    warranty_months INT         NOT NULL DEFAULT 0,
    warranty_end   DATE         NULL,              -- คำนวณจาก install_date + เดือนประกัน
    note           VARCHAR(255) NULL,
    created_by     INT UNSIGNED NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cust (customer_id),
    INDEX idx_warranty (warranty_end),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)      REFERENCES installation_jobs(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- สิทธิ์งานบริการ (ใช้ร่วมกับงานติดตั้ง — install.view/manage)
INSERT INTO permissions (slug, name, module) VALUES
    ('service.view',   'ดูงานบริการ',     'service'),
    ('service.manage', 'จัดการงานบริการ', 'service')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ให้ installer + admin + executive(ดู) มีสิทธิ์
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE (r.slug='installer' AND p.slug IN ('service.view','service.manage'))
   OR (r.slug='executive' AND p.slug='service.view')
   OR (r.slug='sales' AND p.slug='service.view')
ON DUPLICATE KEY UPDATE role_id=role_id;


-- ════════════════════════════════════════════════════════
-- >>> phaseM_claim_asset.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — เชื่อมใบงานบริการ (เคลม) กับทะเบียนอุปกรณ์
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseM_claim_asset.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

ALTER TABLE service_tickets
  ADD COLUMN IF NOT EXISTS asset_id INT UNSIGNED NULL AFTER job_id,
  ADD COLUMN IF NOT EXISTS warranty_status VARCHAR(20) NULL AFTER asset_id;


-- ════════════════════════════════════════════════════════
-- >>> phaseN_warranty_alert.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — ธงแจ้งเตือนต่อประกัน (กันแจ้งซ้ำ)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseN_warranty_alert.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

ALTER TABLE installed_assets
  ADD COLUMN IF NOT EXISTS renewal_alerted_at DATETIME NULL AFTER warranty_end;


-- ════════════════════════════════════════════════════════
-- >>> phaseO_vatmode.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — โหมด VAT ต่อเอกสาร (exclude / include / none)
--    exclude = ราคายังไม่รวม VAT (บวกเพิ่ม 7%)
--    include = ราคารวม VAT แล้ว (ถอด 7% ออกมาแสดง)
--    none    = ไม่มี VAT
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseO_vatmode.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

ALTER TABLE quotations      ADD COLUMN IF NOT EXISTS vat_mode ENUM('exclude','include','none') NOT NULL DEFAULT 'exclude' AFTER discount;
ALTER TABLE sales_orders    ADD COLUMN IF NOT EXISTS vat_mode ENUM('exclude','include','none') NOT NULL DEFAULT 'exclude' AFTER total;
ALTER TABLE invoices        ADD COLUMN IF NOT EXISTS vat_mode ENUM('exclude','include','none') NOT NULL DEFAULT 'exclude' AFTER total;
ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS vat_mode ENUM('exclude','include','none') NOT NULL DEFAULT 'exclude' AFTER total;
ALTER TABLE goods_receipts  ADD COLUMN IF NOT EXISTS vat_mode ENUM('exclude','include','none') NOT NULL DEFAULT 'exclude' AFTER total;


-- ════════════════════════════════════════════════════════
-- >>> phaseP_username.sql
-- ════════════════════════════════════════════════════════
-- ═══════════════════════════════════════════════════════════
--  SolarSell — เปลี่ยน login เป็น username + ชื่ออังกฤษพนักงาน
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseP_username.sql
--    (จากนั้นรัน tools\setup_usernames.php เพื่อตั้งรหัสผ่าน = username)
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ชื่อผู้ใช้ (login)
ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(60) NULL AFTER email;
-- อีเมลไม่บังคับอีกต่อไป (ผู้ใช้ที่สร้างจากพนักงานอาจไม่มีอีเมล)
ALTER TABLE users MODIFY email VARCHAR(190) NULL;

-- ตั้ง username จาก prefix ของอีเมลให้บัญชีเดิม (admin@... → admin)
UPDATE users SET username = SUBSTRING_INDEX(email, '@', 1) WHERE username IS NULL AND email IS NOT NULL;

ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uniq_username (username);

-- ชื่อจริงภาษาอังกฤษของพนักงาน (ใช้สร้าง username)
ALTER TABLE employees ADD COLUMN IF NOT EXISTS name_en VARCHAR(120) NULL AFTER name;


SET FOREIGN_KEY_CHECKS=1;
