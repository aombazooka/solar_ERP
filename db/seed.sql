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
