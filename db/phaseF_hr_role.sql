-- ═══════════════════════════════════════════════════════════
--  SolarSell — สร้าง role ทรัพยากรบุคคล (hr) + สิทธิ์ + user ตัวอย่าง
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseF_hr_role.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- 1) role
INSERT INTO roles (slug, name, description) VALUES
    ('hr', 'ทรัพยากรบุคคล', 'จัดการพนักงาน เวลา ลา OT เงินเดือน')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 2) ผูกสิทธิ์ HR เต็ม + ดูแดชบอร์ด
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'hr'
  AND p.slug IN ('dashboard.view',
                 'hr.view','hr.manage','hr.checkin','hr.payroll','hr.approve','hr.ot','hr.settings')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- 3) user HR (รหัสผ่าน = "password" — เปลี่ยนทันทีหลัง login)
INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'มานี ทรัพยากร', 'hr@solarsell.local',
       '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse',
       r.id, 1
FROM roles r WHERE r.slug = 'hr'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 4) ข้อมูลพนักงานของ HR + ผูกบัญชี (ให้มี personal dashboard ด้วย)
INSERT INTO employees (code, user_id, name, position, department, base_salary, commission_rate)
SELECT 'EMP-0004', u.id, 'มานี ทรัพยากร', 'เจ้าหน้าที่ทรัพยากรบุคคล', 'ทรัพยากรบุคคล', 28000, 0
FROM users u WHERE u.email = 'hr@solarsell.local'
ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), position = VALUES(position), department = VALUES(department);
