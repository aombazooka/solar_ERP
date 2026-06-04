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
