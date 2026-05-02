-- ============================================
-- ShopMate - Complete Database Schema
-- Run this ONCE in Supabase SQL Editor
-- ============================================

-- 1. PRODUCTS
CREATE TABLE IF NOT EXISTS products (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  name TEXT NOT NULL,
  category TEXT DEFAULT 'Other',
  buy_price NUMERIC(10,2) DEFAULT 0,
  sell_price NUMERIC(10,2) DEFAULT 0,
  stock INTEGER DEFAULT 0,
  min_stock INTEGER DEFAULT 5,
  unit TEXT DEFAULT 'pcs',
  barcode TEXT UNIQUE,
  gst_rate NUMERIC(5,2) DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 2. SALES (invoice header)
CREATE TABLE IF NOT EXISTS sales (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  invoice_no TEXT UNIQUE,
  customer_name TEXT,
  customer_phone TEXT,
  subtotal NUMERIC(10,2) DEFAULT 0,
  discount NUMERIC(5,2) DEFAULT 0,
  gst_amount NUMERIC(10,2) DEFAULT 0,
  total NUMERIC(10,2) DEFAULT 0,
  payment_method TEXT DEFAULT 'cash',
  payment_status TEXT DEFAULT 'paid',
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 3. SALE ITEMS (invoice line items)
CREATE TABLE IF NOT EXISTS sale_items (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  sale_id UUID REFERENCES sales(id) ON DELETE CASCADE,
  product_id UUID REFERENCES products(id),
  product_name TEXT,
  quantity NUMERIC(10,3),
  unit_price NUMERIC(10,2),
  total_price NUMERIC(10,2),
  gst_rate NUMERIC(5,2) DEFAULT 0
);

-- 4. PURCHASES (purchase order header)
CREATE TABLE IF NOT EXISTS purchases (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  supplier_name TEXT,
  bill_no TEXT,
  total NUMERIC(10,2) DEFAULT 0,
  payment_status TEXT DEFAULT 'paid',
  purchase_date DATE DEFAULT CURRENT_DATE,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 5. PURCHASE ITEMS (purchase order line items)
CREATE TABLE IF NOT EXISTS purchase_items (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  purchase_id UUID REFERENCES purchases(id) ON DELETE CASCADE,
  product_id UUID REFERENCES products(id),
  product_name TEXT,
  quantity NUMERIC(10,3),
  unit_price NUMERIC(10,2),
  total_price NUMERIC(10,2)
);

-- 6. PAYMENTS
CREATE TABLE IF NOT EXISTS payments (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  type TEXT NOT NULL,
  party_name TEXT,
  amount NUMERIC(10,2),
  method TEXT,
  reference TEXT,
  notes TEXT,
  payment_date DATE DEFAULT CURRENT_DATE,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- IMPORTANT: Disable Row Level Security
-- (allows the app to read/write without login)
-- ============================================
ALTER TABLE products       DISABLE ROW LEVEL SECURITY;
ALTER TABLE sales          DISABLE ROW LEVEL SECURITY;
ALTER TABLE sale_items     DISABLE ROW LEVEL SECURITY;
ALTER TABLE purchases      DISABLE ROW LEVEL SECURITY;
ALTER TABLE purchase_items DISABLE ROW LEVEL SECURITY;
ALTER TABLE payments       DISABLE ROW LEVEL SECURITY;

-- Grant full access to anon role (used by your app)
GRANT USAGE ON SCHEMA public TO anon;
GRANT ALL ON ALL TABLES IN SCHEMA public TO anon;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO anon;

-- ============================================
-- Verify all tables created successfully
-- ============================================
SELECT table_name FROM information_schema.tables
WHERE table_schema = 'public'
ORDER BY table_name;
