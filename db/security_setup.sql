-- ═══════════════════════════════════════════════════════════
--  SolarSell — Hardening ฐานข้อมูล (ทำก่อนใช้งานจริง)
--  อ้างอิง context.md §8 — XAMPP default ไม่ปลอดภัยสำหรับข้อมูลจริง
-- ═══════════════════════════════════════════════════════════
--  ⚠️ แก้ค่า 2 จุดด้านล่างก่อนรัน:
--     'CHANGE_ME_root_pass'  → รหัส root ที่แข็งแรง
--     'CHANGE_ME_app_pass'   → รหัสของ user เฉพาะแอป
--
--  วิธีรัน (ครั้งเดียว):
--     C:\xampp\mysql\bin\mysql -u root < db\security_setup.sql
--
--  หลังรันเสร็จ → แก้ config/config.php ให้ใช้ user 'solarsell_app'
--  แล้วทดสอบ login ระบบอีกครั้ง
-- ═══════════════════════════════════════════════════════════

-- 1) ตั้งรหัสผ่านให้ root (เดิม XAMPP เป็นรหัสว่าง)
ALTER USER 'root'@'localhost' IDENTIFIED BY 'CHANGE_ME_root_pass';

-- 2) สร้าง user เฉพาะแอป (อย่าให้แอปเชื่อมด้วย root)
CREATE USER IF NOT EXISTS 'solarsell_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_app_pass';

-- 3) ให้สิทธิ์เฉพาะ database solarsell เท่านั้น (least privilege)
--    ไม่ให้ DROP/GRANT — แอปไม่จำเป็นต้องใช้
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
  ON solarsell.* TO 'solarsell_app'@'localhost';

FLUSH PRIVILEGES;

-- ═══════════════════════════════════════════════════════════
--  จากนั้นใน config/config.php เปลี่ยนเป็น:
--     'user' => 'solarsell_app',
--     'pass' => 'CHANGE_ME_app_pass',
-- ═══════════════════════════════════════════════════════════
