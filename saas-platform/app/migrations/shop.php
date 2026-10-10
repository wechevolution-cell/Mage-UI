<?php
declare(strict_types=1);
// Tenant (per-shop) schema. Same schema for every shop and every template.
return [
<<<'SQL'
CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT);

CREATE TABLE users (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'editor',
  permissions TEXT,
  status TEXT NOT NULL DEFAULT 'active',
  last_login_at TEXT,
  reset_token_hash TEXT,
  reset_expires INTEGER,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);

CREATE TABLE login_attempts (key TEXT PRIMARY KEY, attempts INTEGER NOT NULL DEFAULT 0, locked_until INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL);
CREATE TABLE rate_limits (key TEXT PRIMARY KEY, window_start INTEGER NOT NULL, hits INTEGER NOT NULL);

CREATE TABLE media (
  id INTEGER PRIMARY KEY,
  path TEXT NOT NULL,
  mime TEXT,
  width INTEGER,
  height INTEGER,
  size INTEGER,
  alt TEXT,
  title TEXT,
  variants TEXT NOT NULL DEFAULT '{}',
  source_url TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE categories (
  id INTEGER PRIMARY KEY,
  parent_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  name TEXT NOT NULL,
  slug TEXT NOT NULL,
  path TEXT NOT NULL,
  description TEXT,
  content TEXT,
  image_id INTEGER,
  sort INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'active',
  seo_title TEXT,
  seo_description TEXT,
  canonical_url TEXT,
  robots TEXT,
  faq TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE UNIQUE INDEX idx_cat_path ON categories(path) WHERE deleted_at IS NULL;
CREATE INDEX idx_cat_parent ON categories(parent_id);

CREATE TABLE products (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT NOT NULL,
  sku TEXT,
  barcode TEXT,
  mpn TEXT,
  short_description TEXT,
  description TEXT,
  price REAL NOT NULL DEFAULT 0,
  sale_price REAL,
  cost_price REAL,
  stock INTEGER NOT NULL DEFAULT 0,
  manage_stock INTEGER NOT NULL DEFAULT 1,
  availability TEXT NOT NULL DEFAULT 'in_stock',
  weight REAL,
  length REAL,
  width REAL,
  height REAL,
  brand TEXT,
  primary_category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  image_id INTEGER,
  is_active INTEGER NOT NULL DEFAULT 0,
  activated_at TEXT,
  featured INTEGER NOT NULL DEFAULT 0,
  tags TEXT,
  vat_rate REAL,
  seo_title TEXT,
  seo_description TEXT,
  focus_keyword TEXT,
  canonical_url TEXT,
  robots TEXT,
  schema_json TEXT,
  faq TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE UNIQUE INDEX idx_prod_slug ON products(slug) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX idx_prod_sku ON products(sku) WHERE deleted_at IS NULL AND sku IS NOT NULL AND sku <> '';
CREATE INDEX idx_prod_active ON products(is_active, deleted_at);
CREATE INDEX idx_prod_cat ON products(primary_category_id);
CREATE INDEX idx_prod_created ON products(created_at);
CREATE INDEX idx_prod_featured ON products(featured, is_active);

CREATE TABLE product_categories (
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
  PRIMARY KEY (product_id, category_id)
);
CREATE INDEX idx_pc_cat ON product_categories(category_id);

CREATE TABLE product_images (
  id INTEGER PRIMARY KEY,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  media_id INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,
  sort INTEGER NOT NULL DEFAULT 0,
  alt TEXT
);
CREATE INDEX idx_pi_prod ON product_images(product_id, sort);

CREATE TABLE product_attributes (
  id INTEGER PRIMARY KEY,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  value TEXT NOT NULL,
  filterable INTEGER NOT NULL DEFAULT 1,
  sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_pa_prod ON product_attributes(product_id);
CREATE INDEX idx_pa_name ON product_attributes(name, value);

CREATE TABLE product_variants (
  id INTEGER PRIMARY KEY,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  sku TEXT,
  name TEXT NOT NULL,
  options TEXT NOT NULL DEFAULT '{}',
  price REAL,
  sale_price REAL,
  stock INTEGER NOT NULL DEFAULT 0,
  barcode TEXT,
  image_id INTEGER,
  sort INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'active'
);
CREATE INDEX idx_pv_prod ON product_variants(product_id);

CREATE TABLE pages (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  slug TEXT NOT NULL,
  content TEXT,
  excerpt TEXT,
  kind TEXT NOT NULL DEFAULT 'page',
  status TEXT NOT NULL DEFAULT 'published',
  seo_title TEXT,
  seo_description TEXT,
  canonical_url TEXT,
  robots TEXT,
  faq TEXT,
  show_in_footer INTEGER NOT NULL DEFAULT 0,
  sort INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE UNIQUE INDEX idx_pages_slug ON pages(slug) WHERE deleted_at IS NULL;

CREATE TABLE posts (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  slug TEXT NOT NULL,
  excerpt TEXT,
  tldr TEXT,
  content TEXT,
  image_id INTEGER,
  author TEXT,
  category TEXT,
  tags TEXT,
  sources TEXT,
  faq TEXT,
  status TEXT NOT NULL DEFAULT 'draft',
  published_at TEXT,
  seo_title TEXT,
  seo_description TEXT,
  focus_keyword TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE UNIQUE INDEX idx_posts_slug ON posts(slug) WHERE deleted_at IS NULL;

CREATE TABLE menus (id INTEGER PRIMARY KEY, location TEXT NOT NULL UNIQUE, name TEXT NOT NULL);
CREATE TABLE menu_items (
  id INTEGER PRIMARY KEY,
  menu_id INTEGER NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
  parent_id INTEGER REFERENCES menu_items(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  type TEXT NOT NULL DEFAULT 'url',
  ref_id INTEGER,
  url TEXT,
  sort INTEGER NOT NULL DEFAULT 0,
  new_tab INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_mi_menu ON menu_items(menu_id, parent_id, sort);

CREATE TABLE popups (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  title TEXT,
  body TEXT,
  button_text TEXT,
  cta_url TEXT,
  collect_email INTEGER NOT NULL DEFAULT 1,
  require_consent INTEGER NOT NULL DEFAULT 1,
  consent_text TEXT,
  image_id INTEGER,
  trigger_type TEXT NOT NULL DEFAULT 'delay',
  delay_seconds INTEGER NOT NULL DEFAULT 5,
  frequency_days INTEGER NOT NULL DEFAULT 7,
  pages TEXT NOT NULL DEFAULT 'home',
  status TEXT NOT NULL DEFAULT 'inactive',
  starts_at TEXT,
  ends_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE TABLE subscribers (
  id INTEGER PRIMARY KEY,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  source TEXT,
  popup_id INTEGER,
  consent INTEGER NOT NULL DEFAULT 0,
  consent_text TEXT,
  consent_at TEXT,
  ip TEXT,
  unsubscribe_token TEXT,
  unsubscribed_at TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE feeds (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  type TEXT NOT NULL DEFAULT 'custom',
  filters TEXT NOT NULL DEFAULT '{}',
  fields TEXT NOT NULL DEFAULT '[]',
  frequency TEXT NOT NULL DEFAULT 'hourly',
  status TEXT NOT NULL DEFAULT 'active',
  last_generated_at TEXT,
  last_count INTEGER,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE TABLE feed_logs (
  id INTEGER PRIMARY KEY,
  feed_id INTEGER NOT NULL REFERENCES feeds(id) ON DELETE CASCADE,
  status TEXT NOT NULL,
  items INTEGER,
  duration_ms INTEGER,
  message TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE imports (
  id INTEGER PRIMARY KEY,
  type TEXT NOT NULL,
  filename TEXT,
  path TEXT,
  status TEXT NOT NULL DEFAULT 'uploaded',
  mapping TEXT NOT NULL DEFAULT '{}',
  options TEXT NOT NULL DEFAULT '{}',
  headers TEXT NOT NULL DEFAULT '[]',
  total_rows INTEGER NOT NULL DEFAULT 0,
  processed_rows INTEGER NOT NULL DEFAULT 0,
  created_count INTEGER NOT NULL DEFAULT 0,
  updated_count INTEGER NOT NULL DEFAULT 0,
  skipped_count INTEGER NOT NULL DEFAULT 0,
  error_count INTEGER NOT NULL DEFAULT 0,
  activated_count INTEGER NOT NULL DEFAULT 0,
  quota_blocked_count INTEGER NOT NULL DEFAULT 0,
  user_id INTEGER,
  started_at TEXT,
  finished_at TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE import_rows (
  id INTEGER PRIMARY KEY,
  import_id INTEGER NOT NULL REFERENCES imports(id) ON DELETE CASCADE,
  row_no INTEGER NOT NULL,
  data TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  message TEXT
);
CREATE INDEX idx_ir_import ON import_rows(import_id, status, row_no);
CREATE TABLE import_logs (
  id INTEGER PRIMARY KEY,
  import_id INTEGER NOT NULL REFERENCES imports(id) ON DELETE CASCADE,
  level TEXT NOT NULL DEFAULT 'info',
  row_no INTEGER,
  message TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_il_import ON import_logs(import_id);

CREATE TABLE customers (
  id INTEGER PRIMARY KEY,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  first_name TEXT,
  last_name TEXT,
  phone TEXT,
  password_hash TEXT,
  marketing_consent INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'active',
  reset_token_hash TEXT,
  reset_expires INTEGER,
  last_login_at TEXT,
  notes TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE TABLE customer_addresses (
  id INTEGER PRIMARY KEY,
  customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
  label TEXT,
  first_name TEXT,
  last_name TEXT,
  phone TEXT,
  address TEXT,
  city TEXT,
  postcode TEXT,
  region TEXT,
  country TEXT NOT NULL DEFAULT 'GR',
  is_default INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

CREATE TABLE orders (
  id INTEGER PRIMARY KEY,
  number TEXT NOT NULL UNIQUE,
  access_token TEXT NOT NULL,
  customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
  email TEXT NOT NULL,
  phone TEXT,
  first_name TEXT,
  last_name TEXT,
  shipping_address TEXT NOT NULL DEFAULT '{}',
  billing TEXT NOT NULL DEFAULT '{}',
  shipping_zone TEXT,
  shipping_method TEXT,
  shipping_cost REAL NOT NULL DEFAULT 0,
  payment_method TEXT,
  payment_fee REAL NOT NULL DEFAULT 0,
  payment_status TEXT NOT NULL DEFAULT 'pending',
  status TEXT NOT NULL DEFAULT 'pending',
  shipping_status TEXT NOT NULL DEFAULT 'not_shipped',
  tracking_number TEXT,
  subtotal REAL NOT NULL DEFAULT 0,
  discount REAL NOT NULL DEFAULT 0,
  tax_total REAL NOT NULL DEFAULT 0,
  total REAL NOT NULL DEFAULT 0,
  currency TEXT NOT NULL DEFAULT 'EUR',
  customer_note TEXT,
  gift_wrap INTEGER NOT NULL DEFAULT 0,
  ip TEXT,
  user_agent TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE INDEX idx_orders_created ON orders(created_at);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_customer ON orders(customer_id);

CREATE TABLE order_items (
  id INTEGER PRIMARY KEY,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_id INTEGER,
  variant_id INTEGER,
  name TEXT NOT NULL,
  sku TEXT,
  options TEXT,
  unit_price REAL NOT NULL,
  qty INTEGER NOT NULL,
  total REAL NOT NULL
);
CREATE INDEX idx_oi_order ON order_items(order_id);
CREATE INDEX idx_oi_product ON order_items(product_id);

CREATE TABLE order_history (
  id INTEGER PRIMARY KEY,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  type TEXT NOT NULL DEFAULT 'status',
  status TEXT,
  note TEXT,
  actor TEXT,
  notify_customer INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_oh_order ON order_history(order_id);

CREATE TABLE wishlist (
  customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL,
  PRIMARY KEY (customer_id, product_id)
);

CREATE TABLE redirects (
  id INTEGER PRIMARY KEY,
  source TEXT NOT NULL,
  target TEXT,
  code INTEGER NOT NULL DEFAULT 301,
  match_type TEXT NOT NULL DEFAULT 'exact',
  active INTEGER NOT NULL DEFAULT 1,
  hits INTEGER NOT NULL DEFAULT 0,
  last_hit_at TEXT,
  note TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_redir_source ON redirects(source, active);

CREATE TABLE not_found_log (
  id INTEGER PRIMARY KEY,
  path TEXT NOT NULL UNIQUE,
  hits INTEGER NOT NULL DEFAULT 1,
  referrer TEXT,
  ignored INTEGER NOT NULL DEFAULT 0,
  last_hit_at TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE contact_messages (
  id INTEGER PRIMARY KEY,
  name TEXT,
  email TEXT,
  phone TEXT,
  subject TEXT,
  message TEXT,
  consent INTEGER NOT NULL DEFAULT 0,
  ip TEXT,
  read_at TEXT,
  created_at TEXT NOT NULL
);
SQL,
];
