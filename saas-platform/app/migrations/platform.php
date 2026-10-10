<?php
declare(strict_types=1);
// Platform (control plane) schema. Append new migrations; never edit applied ones.
return [
<<<'SQL'
CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT);

CREATE TABLE super_admins (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'active',
  last_login_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);

CREATE TABLE subscription_plans (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  description TEXT,
  price REAL NOT NULL DEFAULT 0,
  setup_fee REAL NOT NULL DEFAULT 0,
  currency TEXT NOT NULL DEFAULT 'EUR',
  billing_period_months INTEGER NOT NULL DEFAULT 1,
  active_product_limit INTEGER NOT NULL,
  stored_product_limit INTEGER NOT NULL DEFAULT 10000,
  staff_limit INTEGER NOT NULL DEFAULT 3,
  storage_limit_mb INTEGER NOT NULL DEFAULT 2048,
  duration_options TEXT NOT NULL DEFAULT '[1,3,6,12]',
  features TEXT NOT NULL DEFAULT '{}',
  status TEXT NOT NULL DEFAULT 'active',
  sort INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE TABLE templates (
  id INTEGER PRIMARY KEY,
  key TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  industry TEXT,
  description TEXT,
  enabled INTEGER NOT NULL DEFAULT 1,
  allowed_plans TEXT,
  sort INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE TABLE shops (
  id INTEGER PRIMARY KEY,
  slug TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  company_name TEXT,
  owner_name TEXT,
  email TEXT,
  phone TEXT,
  vat_number TEXT,
  address TEXT,
  notes TEXT,
  plan_id INTEGER REFERENCES subscription_plans(id),
  template_key TEXT,
  status TEXT NOT NULL DEFAULT 'pending',
  active_limit_override INTEGER,
  admin_login_enabled INTEGER NOT NULL DEFAULT 1,
  allow_template_choice INTEGER NOT NULL DEFAULT 0,
  allowed_templates TEXT,
  feature_overrides TEXT NOT NULL DEFAULT '{}',
  quota_mode TEXT NOT NULL DEFAULT 'normal',
  hosting_status TEXT NOT NULL DEFAULT 'pending',
  hosting_notes TEXT,
  locked_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE INDEX idx_shops_status ON shops(status);

CREATE TABLE shop_domains (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  domain TEXT NOT NULL UNIQUE COLLATE NOCASE,
  is_primary INTEGER NOT NULL DEFAULT 0,
  ssl_status TEXT NOT NULL DEFAULT 'pending',
  dns_status TEXT NOT NULL DEFAULT 'pending',
  hosting_status TEXT NOT NULL DEFAULT 'pending',
  verified_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_domains_shop ON shop_domains(shop_id);

CREATE TABLE payment_providers (
  id INTEGER PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  enabled INTEGER NOT NULL DEFAULT 0,
  config TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL
);

CREATE TABLE subscriptions (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  plan_id INTEGER NOT NULL REFERENCES subscription_plans(id),
  status TEXT NOT NULL DEFAULT 'active',
  start_date TEXT NOT NULL,
  end_date TEXT NOT NULL,
  duration_months INTEGER NOT NULL,
  price REAL NOT NULL DEFAULT 0,
  currency TEXT NOT NULL DEFAULT 'EUR',
  provider_code TEXT NOT NULL DEFAULT 'manual',
  provider_ref TEXT,
  auto_renew INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE INDEX idx_subs_shop ON subscriptions(shop_id, status);

CREATE TABLE subscription_history (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  subscription_id INTEGER,
  action TEXT NOT NULL,
  plan_id INTEGER,
  plan_name TEXT,
  duration_months INTEGER,
  start_date TEXT,
  end_date TEXT,
  price REAL,
  actor_type TEXT,
  actor_id INTEGER,
  note TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_subhist_shop ON subscription_history(shop_id);

CREATE TABLE payments (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  subscription_id INTEGER,
  provider_code TEXT NOT NULL DEFAULT 'manual',
  kind TEXT NOT NULL DEFAULT 'subscription',
  amount REAL NOT NULL,
  currency TEXT NOT NULL DEFAULT 'EUR',
  status TEXT NOT NULL DEFAULT 'pending',
  reference TEXT,
  paid_at TEXT,
  meta TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL
);
CREATE INDEX idx_payments_shop ON payments(shop_id);

CREATE TABLE invoices (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  payment_id INTEGER,
  number TEXT UNIQUE,
  amount REAL NOT NULL,
  currency TEXT NOT NULL DEFAULT 'EUR',
  status TEXT NOT NULL DEFAULT 'draft',
  issued_at TEXT,
  meta TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL
);

CREATE TABLE audit_logs (
  id INTEGER PRIMARY KEY,
  actor_type TEXT NOT NULL,
  actor_id INTEGER,
  actor_name TEXT,
  impersonator_id INTEGER,
  shop_id INTEGER,
  action TEXT NOT NULL,
  entity_type TEXT,
  entity_id TEXT,
  outcome TEXT NOT NULL DEFAULT 'success',
  ip TEXT,
  details TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_audit_shop ON audit_logs(shop_id, created_at);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

CREATE TABLE notifications (
  id INTEGER PRIMARY KEY,
  type TEXT NOT NULL,
  shop_id INTEGER,
  title TEXT NOT NULL,
  body TEXT,
  dedupe_key TEXT UNIQUE,
  read_at TEXT,
  emailed_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_notif_read ON notifications(read_at);

CREATE TABLE login_attempts (key TEXT PRIMARY KEY, attempts INTEGER NOT NULL DEFAULT 0, locked_until INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL);
CREATE TABLE rate_limits (key TEXT PRIMARY KEY, window_start INTEGER NOT NULL, hits INTEGER NOT NULL);

CREATE TABLE impersonation_tokens (
  token_hash TEXT PRIMARY KEY,
  super_admin_id INTEGER NOT NULL,
  shop_id INTEGER NOT NULL,
  expires_at INTEGER NOT NULL,
  used_at TEXT
);

CREATE TABLE mail_log (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER,
  to_email TEXT,
  subject TEXT,
  driver TEXT,
  status TEXT,
  error TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE jobs (
  id INTEGER PRIMARY KEY,
  type TEXT NOT NULL,
  shop_id INTEGER,
  payload TEXT NOT NULL DEFAULT '{}',
  status TEXT NOT NULL DEFAULT 'queued',
  attempts INTEGER NOT NULL DEFAULT 0,
  run_after INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE INDEX idx_jobs_status ON jobs(status, run_after);
SQL,
];
