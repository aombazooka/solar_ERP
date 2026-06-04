-- ═══════════════════════════════════════════════════════════
--  SolarSell — เพิ่มสถานะ "ยกเลิก" (cancelled) ให้คำขอลา/OT
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseE_cancel.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

ALTER TABLE leave_requests
  MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending';

ALTER TABLE ot_requests
  MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending';
