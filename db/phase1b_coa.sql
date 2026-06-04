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
