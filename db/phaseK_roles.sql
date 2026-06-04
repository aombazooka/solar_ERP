-- ═══════════════════════════════════════════════════════════
--  SolarSell — เพิ่ม Role: Supervisor / Executive / Staff
--    + สิทธิ์อนุมัติเฉพาะทีม (hr.approve_team) ตาม context §5.1
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseK_roles.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ─── permission ใหม่ ───
INSERT INTO permissions (slug, name, module) VALUES
    ('hr.approve_team', 'อนุมัติลา/OT เฉพาะทีม', 'hr')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── roles ใหม่ ───
INSERT INTO roles (slug, name, description) VALUES
    ('supervisor', 'หัวหน้างาน',   'อนุมัติลา/OT ของทีมตัวเอง + self-service'),
    ('executive',  'ผู้บริหาร',     'ดูข้อมูล/รายงานทุกฝ่าย (อ่านอย่างเดียว)'),
    ('staff',      'พนักงานทั่วไป', 'self-service: ตอกบัตร/ลา/OT/สลิป')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── grant สิทธิ์ ───
-- supervisor: self-service + ดูข้อมูล HR + อนุมัติเฉพาะทีม
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='supervisor' AND p.slug IN ('dashboard.view','hr.checkin','hr.view','hr.approve_team')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- executive: ดูอย่างเดียวทุกฝ่าย (เฉพาะ *.view ไม่มี manage/create)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='executive' AND p.slug IN
  ('dashboard.view','sales.view','install.view','inventory.view','finance.view','customer.view','hr.view')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- staff: self-service ล้วน
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='staff' AND p.slug IN ('dashboard.view','hr.checkin')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- HR ได้ hr.approve_team ด้วย (เข้ากล่องรออนุมัติได้ — เห็นทั้งองค์กรเพราะมี hr.approve อยู่แล้ว)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='hr' AND p.slug='hr.approve_team'
ON DUPLICATE KEY UPDATE role_id=role_id;

-- ─── user ตัวอย่าง (รหัส = "password") ───
INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'วิชัย หัวหน้าทีม', 'supervisor@solarsell.local', '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse', r.id, 1
FROM roles r WHERE r.slug='supervisor' ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'ประธาน บริหาร', 'exec@solarsell.local', '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse', r.id, 1
FROM roles r WHERE r.slug='executive' ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO users (name, email, password_hash, role_id, is_active)
SELECT 'สมหมาย พนักงาน', 'staff@solarsell.local', '$2y$10$ovtYsGt2pTFCv/Dt01xf5ORvy46.wmbL.sb/ViwkNSxd69is2CXse', r.id, 1
FROM roles r WHERE r.slug='staff' ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ─── employees + ผูกบัญชี (supervisor & staff อยู่ทีม A เพื่อทดสอบ) ───
INSERT INTO employees (code, user_id, name, position, department, team, base_salary)
SELECT 'EMP-0005', u.id, 'วิชัย หัวหน้าทีม', 'หัวหน้าทีมติดตั้ง', 'ติดตั้ง', 'A', 30000
FROM users u WHERE u.email='supervisor@solarsell.local'
ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), team=VALUES(team);

INSERT INTO employees (code, user_id, name, position, department, team, base_salary)
SELECT 'EMP-0006', u.id, 'สมหมาย พนักงาน', 'ช่างติดตั้ง', 'ติดตั้ง', 'A', 16000
FROM users u WHERE u.email='staff@solarsell.local'
ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), team=VALUES(team);
