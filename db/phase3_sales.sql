-- ═══════════════════════════════════════════════════════════
--  SolarSell — Phase 3: Sales (quotation → order → delivery)
--    C:\xampp\mysql\bin\mysql -u root solarsell < db\phase3_sales.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── quotations (ใบเสนอราคา : header) ──────────────────────
CREATE TABLE IF NOT EXISTS quotations (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no       VARCHAR(30)  NOT NULL UNIQUE,    -- QT-2569-0001
    customer_id  INT UNSIGNED NOT NULL,
    status       ENUM('draft','sent','accepted','rejected','converted') NOT NULL DEFAULT 'draft',
    system_type  ENUM('on_grid','hybrid','off_grid') NULL,
    capacity_kwp DECIMAL(10,2) NULL,
    subtotal     DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount     DECIMAL(14,2) NOT NULL DEFAULT 0,
    vat          DECIMAL(14,2) NOT NULL DEFAULT 0,
    total        DECIMAL(14,2) NOT NULL DEFAULT 0,
    valid_until  DATE         NULL,
    note         VARCHAR(500) NULL,
    created_by   INT UNSIGNED NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── quotation_items (รายการในใบเสนอราคา) ─────────────────
CREATE TABLE IF NOT EXISTS quotation_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_id  INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED NULL,             -- NULL = บรรทัดอิสระ (free text)
    description   VARCHAR(255) NOT NULL,
    qty           DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total    DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)   REFERENCES products(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── sales_orders (ใบสั่งขาย : header) ─────────────────────
CREATE TABLE IF NOT EXISTS sales_orders (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_no       VARCHAR(30)  NOT NULL UNIQUE,    -- SO-2569-0001
    quotation_id INT UNSIGNED NULL,
    customer_id  INT UNSIGNED NOT NULL,
    status       ENUM('pending','delivered','invoiced','cancelled') NOT NULL DEFAULT 'pending',
    total        DECIMAL(14,2) NOT NULL DEFAULT 0,
    delivered_at TIMESTAMP    NULL,
    note         VARCHAR(500) NULL,
    created_by   INT UNSIGNED NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id),
    FOREIGN KEY (customer_id)  REFERENCES customers(id),
    FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── sales_order_items ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS sales_order_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id      INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED NULL,
    description   VARCHAR(255) NOT NULL,
    qty           DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total    DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (order_id)   REFERENCES sales_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
