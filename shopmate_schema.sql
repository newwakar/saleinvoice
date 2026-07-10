-- ============================================================
-- ShopMate - COMPLETE MySQL Schema (Latest Version)
-- Includes: HSN Code, Narration, GST Rate on sale items,
--           shop settings table
-- ============================================================
-- Run this ONCE in phpMyAdmin > SQL Editor
-- Safe to run even if tables already exist (uses IF NOT EXISTS)
-- ============================================================

CREATE DATABASE IF NOT EXISTS shopmate
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE shopmate;

-- ════════════════════════════════════════════
-- 1. PRODUCTS
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS products (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255) NOT NULL,
  category    VARCHAR(100) DEFAULT 'Other',
  buy_price   DECIMAL(10,2) DEFAULT 0.00,
  sell_price  DECIMAL(10,2) DEFAULT 0.00,
  stock       INT DEFAULT 0,
  min_stock   INT DEFAULT 5,
  unit        VARCHAR(20) DEFAULT 'pcs',
  barcode     VARCHAR(100) UNIQUE,
  gst_rate    DECIMAL(5,2) DEFAULT 0.00,
  hsn_code    VARCHAR(20) DEFAULT '',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ════════════════════════════════════════════
-- 2. SALES (Invoice Header)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS sales (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no       VARCHAR(50) UNIQUE,
  customer_name    VARCHAR(200),
  customer_phone   VARCHAR(20),
  subtotal         DECIMAL(10,2) DEFAULT 0.00,
  discount         DECIMAL(5,2)  DEFAULT 0.00,
  gst_amount       DECIMAL(10,2) DEFAULT 0.00,
  total            DECIMAL(10,2) DEFAULT 0.00,
  payment_method   VARCHAR(20)   DEFAULT 'cash',
  payment_status   VARCHAR(20)   DEFAULT 'paid',
  narration        TEXT,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ════════════════════════════════════════════
-- 3. SALE ITEMS (Invoice Line Items)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS sale_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  sale_id       INT,
  product_id    INT,
  product_name  VARCHAR(255),
  quantity      DECIMAL(10,3),
  unit_price    DECIMAL(10,2),
  total_price   DECIMAL(10,2),
  gst_rate      DECIMAL(5,2) DEFAULT 0.00,
  hsn_code      VARCHAR(20)  DEFAULT '',
  FOREIGN KEY (sale_id)    REFERENCES sales(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ════════════════════════════════════════════
-- 4. PURCHASES (Purchase Order Header)
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS purchases (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  supplier_name    VARCHAR(200),
  bill_no          VARCHAR(100),
  total            DECIMAL(10,2) DEFAULT 0.00,
  payment_status   VARCHAR(20)   DEFAULT 'paid',
  purchase_date    DATE          DEFAULT (CURRENT_DATE),
  narration        TEXT,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ════════════════════════════════════════════
-- 5. PURCHASE ITEMS
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS purchase_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id   INT,
  product_id    INT,
  product_name  VARCHAR(255),
  quantity      DECIMAL(10,3),
  unit_price    DECIMAL(10,2),
  total_price   DECIMAL(10,2),
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ════════════════════════════════════════════
-- 6. PAYMENTS
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS payments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  type          VARCHAR(20)   NOT NULL,
  party_name    VARCHAR(200),
  amount        DECIMAL(10,2),
  method        VARCHAR(50),
  reference     VARCHAR(200),
  notes         TEXT,
  payment_date  DATE DEFAULT (CURRENT_DATE),
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ════════════════════════════════════════════
-- 7. SHOP SETTINGS
--    Stores GSTIN, address, state etc.
-- ════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS shop_settings (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  setting_key  VARCHAR(100) UNIQUE NOT NULL,
  setting_val  TEXT,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default shop settings
INSERT IGNORE INTO shop_settings (setting_key, setting_val) VALUES
  ('shop_name',    'Apple Electricals'),
  ('shop_address', ''),
  ('shop_phone',   ''),
  ('shop_email',   ''),
  ('shop_gstin',   ''),
  ('shop_state',   '36-Telangana'),
  ('shop_city',    '');

-- ════════════════════════════════════════════
-- IF TABLES ALREADY EXIST — run these ALTER
-- statements to add missing columns safely
-- ════════════════════════════════════════════

-- Products: add hsn_code if missing
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS hsn_code VARCHAR(20) DEFAULT '' AFTER gst_rate;

-- Sales: add narration if missing
ALTER TABLE sales
  ADD COLUMN IF NOT EXISTS narration TEXT AFTER payment_status;

-- Sale items: add gst_rate and hsn_code if missing
ALTER TABLE sale_items
  ADD COLUMN IF NOT EXISTS gst_rate  DECIMAL(5,2) DEFAULT 0.00 AFTER total_price,
  ADD COLUMN IF NOT EXISTS hsn_code  VARCHAR(20)  DEFAULT ''   AFTER gst_rate;

-- Purchases: add narration if missing
ALTER TABLE purchases
  ADD COLUMN IF NOT EXISTS narration TEXT AFTER payment_status;

-- ════════════════════════════════════════════
-- VERIFY — Check all tables and columns
-- ════════════════════════════════════════════
SELECT
  TABLE_NAME,
  GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION SEPARATOR ', ') AS columns
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'shopmate'
GROUP BY TABLE_NAME
ORDER BY TABLE_NAME;


-- ════════════════════════════════════════════
-- Customer fields added for GST invoices
-- ════════════════════════════════════════════
ALTER TABLE sales
  ADD COLUMN IF NOT EXISTS customer_address VARCHAR(300) DEFAULT '' AFTER customer_phone,
  ADD COLUMN IF NOT EXISTS customer_gstin   VARCHAR(20)  DEFAULT '' AFTER customer_address,
  ADD COLUMN IF NOT EXISTS customer_state   VARCHAR(50)  DEFAULT '' AFTER customer_gstin;
