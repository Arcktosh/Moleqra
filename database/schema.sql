CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(180) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(50) NOT NULL,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(80) NULL,
  format VARCHAR(180) NOT NULL,
  status VARCHAR(60) NOT NULL DEFAULT 'Supplier qualification',
  purity_label VARCHAR(40) NULL,
  description TEXT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  INDEX idx_products_public_sort (is_public, sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  region VARCHAR(80) NULL,
  contact_name VARCHAR(120) NULL,
  email VARCHAR(180) NULL,
  website VARCHAR(255) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'Prospect',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_suppliers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coa_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  batch_number VARCHAR(100) NOT NULL,
  lab_name VARCHAR(160) NULL,
  purity_label VARCHAR(40) NULL,
  tested_at DATE NULL,
  file_path VARCHAR(255) NOT NULL,
  sha256 CHAR(64) NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_coa_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  INDEX idx_coa_public (is_public, created_at),
  INDEX idx_coa_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  topic VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(180) NOT NULL,
  company VARCHAR(150) NULL,
  message TEXT NOT NULL,
  ip_hash CHAR(64) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'new',
  admin_notes TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_enquiries_submitted_at (submitted_at),
  INDEX idx_enquiries_topic (topic),
  INDEX idx_enquiries_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supplier operations layer
CREATE TABLE IF NOT EXISTS supplier_qualifications (
  supplier_id BIGINT UNSIGNED NOT NULL,
  business_verified TINYINT(1) NOT NULL DEFAULT 0,
  sample_coa_received TINYINT(1) NOT NULL DEFAULT 0,
  batch_specific_coa TINYINT(1) NOT NULL DEFAULT 0,
  hplc_present TINYINT(1) NOT NULL DEFAULT 0,
  mass_spec_present TINYINT(1) NOT NULL DEFAULT 0,
  coa_identity_matches TINYINT(1) NOT NULL DEFAULT 0,
  ruo_label_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  private_label_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  shipping_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  payment_terms_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  returns_process_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  qualification_notes TEXT NULL,
  reviewed_at DATE NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (supplier_id),
  CONSTRAINT fk_supplier_qualifications_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  supplier_sku VARCHAR(100) NULL,
  availability VARCHAR(40) NOT NULL DEFAULT 'Unknown',
  wholesale_price DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  moq_units INT UNSIGNED NULL,
  moq_value DECIMAL(12,2) NULL,
  lead_time_days INT UNSIGNED NULL,
  coa_available TINYINT(1) NOT NULL DEFAULT 0,
  private_label TINYINT(1) NOT NULL DEFAULT 0,
  dropship TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_supplier_products_pair (supplier_id, product_id),
  INDEX idx_supplier_products_product (product_id),
  CONSTRAINT fk_supplier_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
  CONSTRAINT fk_supplier_products_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_outreach (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id BIGINT UNSIGNED NOT NULL,
  contacted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  channel VARCHAR(30) NOT NULL DEFAULT 'Email',
  subject VARCHAR(180) NULL,
  notes TEXT NOT NULL,
  outcome VARCHAR(40) NOT NULL DEFAULT 'Awaiting response',
  next_follow_up DATE NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_supplier_outreach_supplier (supplier_id, contacted_at),
  INDEX idx_supplier_outreach_followup (next_follow_up, outcome),
  CONSTRAINT fk_supplier_outreach_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
  CONSTRAINT fk_supplier_outreach_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_test_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id BIGINT UNSIGNED NOT NULL,
  order_ref VARCHAR(100) NULL,
  ordered_at DATE NOT NULL,
  received_at DATE NULL,
  amount DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  status VARCHAR(40) NOT NULL DEFAULT 'Planned',
  courier VARCHAR(100) NULL,
  tracking_ref VARCHAR(160) NULL,
  packaging_condition TINYINT UNSIGNED NULL,
  documentation_complete TINYINT(1) NOT NULL DEFAULT 0,
  batch_matches_coa TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_supplier_test_orders_supplier (supplier_id, ordered_at),
  INDEX idx_supplier_test_orders_status (status),
  CONSTRAINT fk_supplier_test_orders_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
  CONSTRAINT chk_supplier_test_orders_packaging CHECK (packaging_condition IS NULL OR packaging_condition BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS procurement_scenarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_product_id BIGINT UNSIGNED NOT NULL,
  scenario_name VARCHAR(120) NOT NULL DEFAULT 'Current quote',
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(14,4) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  fx_to_base DECIMAL(14,6) NOT NULL DEFAULT 1.000000,
  shipping_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  duty_tax_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  lab_testing_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  packaging_label_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_fee_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  other_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  quoted_at DATE NULL,
  valid_until DATE NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_procurement_scenarios_offer (supplier_product_id),
  INDEX idx_procurement_scenarios_validity (valid_until),
  CONSTRAINT fk_procurement_scenarios_offer FOREIGN KEY (supplier_product_id) REFERENCES supplier_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_market_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  market_code VARCHAR(12) NOT NULL,
  review_status VARCHAR(50) NOT NULL DEFAULT 'Review required',
  listing_hold TINYINT(1) NOT NULL DEFAULT 1,
  review_reference VARCHAR(180) NULL,
  evidence_notes TEXT NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_market_reviews (product_id, market_code),
  INDEX idx_product_market_reviews_status (market_code, review_status, listing_hold),
  CONSTRAINT fk_product_market_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_market_reviews_admin FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS procurement_decisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  market_code VARCHAR(12) NOT NULL,
  preferred_supplier_product_id BIGINT UNSIGNED NULL,
  decision_status VARCHAR(50) NOT NULL DEFAULT 'Evaluating',
  decision_notes TEXT NULL,
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_procurement_decisions (product_id, market_code),
  INDEX idx_procurement_decisions_status (market_code, decision_status),
  CONSTRAINT fk_procurement_decisions_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_procurement_decisions_offer FOREIGN KEY (preferred_supplier_product_id) REFERENCES supplier_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_procurement_decisions_admin FOREIGN KEY (decided_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
