-- ═══════════════════════════════════════════════════════════
--  SolarSell — เชื่อมใบงานบริการ (เคลม) กับทะเบียนอุปกรณ์
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseM_claim_asset.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

ALTER TABLE service_tickets
  ADD COLUMN IF NOT EXISTS asset_id INT UNSIGNED NULL AFTER job_id,
  ADD COLUMN IF NOT EXISTS warranty_status VARCHAR(20) NULL AFTER asset_id;
