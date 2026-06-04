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
