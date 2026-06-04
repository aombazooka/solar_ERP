-- ═══════════════════════════════════════════════════════════
--  SolarSell — ธงแจ้งเตือนต่อประกัน (กันแจ้งซ้ำ)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseN_warranty_alert.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

ALTER TABLE installed_assets
  ADD COLUMN IF NOT EXISTS renewal_alerted_at DATETIME NULL AFTER warranty_end;
