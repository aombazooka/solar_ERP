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
