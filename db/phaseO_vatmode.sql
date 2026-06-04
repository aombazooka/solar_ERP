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
