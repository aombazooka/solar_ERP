-- ═══════════════════════════════════════════════════════════
--  SolarSell — งานบริการหลังการขาย (O&M) + ทะเบียนอุปกรณ์/รับประกัน
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseL_service.sql
-- ═══════════════════════════════════════════════════════════
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── ใบงานบริการ (Service Ticket) ──────────────────────────
CREATE TABLE IF NOT EXISTS service_tickets (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no         VARCHAR(30)  NOT NULL UNIQUE,    -- SRV-2569-0001
    customer_id    INT UNSIGNED NOT NULL,
    job_id         INT UNSIGNED NULL,               -- งานติดตั้งที่เกี่ยวข้อง
    ticket_type    ENUM('maintenance','repair','claim','inspection') NOT NULL DEFAULT 'repair',
    priority       ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status         ENUM('open','in_progress','resolved','closed','cancelled') NOT NULL DEFAULT 'open',
    title          VARCHAR(190) NOT NULL,
    description    TEXT         NULL,
    assigned_team  VARCHAR(40)  NULL,
    scheduled_date DATE         NULL,
    resolved_at    TIMESTAMP    NULL,
    resolution     VARCHAR(500) NULL,
    created_by     INT UNSIGNED NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status, priority),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (job_id)      REFERENCES installation_jobs(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ทะเบียนอุปกรณ์ที่ติดตั้ง + การรับประกัน ───────────────
CREATE TABLE IF NOT EXISTS installed_assets (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT UNSIGNED NOT NULL,
    job_id         INT UNSIGNED NULL,
    product_id     INT UNSIGNED NULL,
    category       ENUM('panel','inverter','battery','other') NOT NULL DEFAULT 'other',
    brand          VARCHAR(80)  NULL,
    serial_no      VARCHAR(100) NOT NULL,
    install_date   DATE         NULL,
    warranty_months INT         NOT NULL DEFAULT 0,
    warranty_end   DATE         NULL,              -- คำนวณจาก install_date + เดือนประกัน
    note           VARCHAR(255) NULL,
    created_by     INT UNSIGNED NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cust (customer_id),
    INDEX idx_warranty (warranty_end),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)      REFERENCES installation_jobs(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- สิทธิ์งานบริการ (ใช้ร่วมกับงานติดตั้ง — install.view/manage)
INSERT INTO permissions (slug, name, module) VALUES
    ('service.view',   'ดูงานบริการ',     'service'),
    ('service.manage', 'จัดการงานบริการ', 'service')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ให้ installer + admin + executive(ดู) มีสิทธิ์
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE (r.slug='installer' AND p.slug IN ('service.view','service.manage'))
   OR (r.slug='executive' AND p.slug='service.view')
   OR (r.slug='sales' AND p.slug='service.view')
ON DUPLICATE KEY UPDATE role_id=role_id;
