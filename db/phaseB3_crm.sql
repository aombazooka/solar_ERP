-- ═══════════════════════════════════════════════════════════
--  SolarSell — Group B3: Leads (CRM) + งานติดตั้ง (Installation jobs)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phaseB3_crm.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── leads (ลูกค้าสนใจ) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS leads (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(190) NOT NULL,
    phone        VARCHAR(30)  NULL,
    source       VARCHAR(60)  NULL,             -- Facebook/LINE/แนะนำ/Walk-in
    interest_system ENUM('on_grid','hybrid','off_grid') NULL,
    interest_kwp DECIMAL(10,2) NULL,
    est_value    DECIMAL(14,2) NULL,            -- มูลค่าคาดการณ์
    status       ENUM('new','contacted','quoted','won','lost') NOT NULL DEFAULT 'new',
    note         VARCHAR(500) NULL,
    converted_customer_id INT UNSIGNED NULL,
    assigned_to  INT UNSIGNED NULL,             -- user
    created_by   INT UNSIGNED NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (converted_customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── installation_jobs (งานติดตั้ง) ───────────────────────
CREATE TABLE IF NOT EXISTS installation_jobs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no        VARCHAR(30)  NOT NULL UNIQUE,  -- JOB-2569-0001
    order_id      INT UNSIGNED NULL,             -- จากใบสั่งขาย
    customer_id   INT UNSIGNED NOT NULL,
    site_id       INT UNSIGNED NULL,             -- จุดหน้างาน (geofencing)
    system_type   ENUM('on_grid','hybrid','off_grid') NULL,
    capacity_kwp  DECIMAL(10,2) NULL,
    team          VARCHAR(40)  NULL,
    scheduled_date DATE        NULL,
    status        ENUM('pending','in_progress','done','cancelled') NOT NULL DEFAULT 'pending',
    progress      TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- 0-100
    note          VARCHAR(500) NULL,
    created_by    INT UNSIGNED NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)    REFERENCES sales_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (site_id)     REFERENCES work_sites(id)   ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ตัวอย่าง leads
INSERT INTO leads (name, phone, source, interest_system, interest_kwp, est_value, status, created_by) VALUES
    ('บริษัท กรีนพาวเวอร์ จำกัด', '02-555-1234', 'Facebook', 'on_grid', 40, 1200000, 'new', 1),
    ('คุณประยงค์ มีสุข',          '081-222-3333', 'แนะนำ',    'hybrid',  8,  280000,  'contacted', 1),
    ('โรงเรียนชัยวิทยา',          '02-777-8888', 'LINE',     'on_grid', 20, 520000,  'quoted', 1);
