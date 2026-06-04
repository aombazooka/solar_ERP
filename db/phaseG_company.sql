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
