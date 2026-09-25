CREATE TABLE IF NOT EXISTS product_storefront (
  product_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(160) NULL,
  short_description VARCHAR(500) NULL,
  meta_title VARCHAR(180) NULL,
  meta_description VARCHAR(320) NULL,
  search_keywords VARCHAR(500) NULL,
  low_stock_threshold DECIMAL(12,3) NOT NULL DEFAULT 5,
  stock_message VARCHAR(160) NULL,
  seo_indexable TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id),
  UNIQUE KEY uq_product_storefront_slug (slug),
  CONSTRAINT fk_product_storefront_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(70) NOT NULL,
  label VARCHAR(120) NOT NULL,
  units_per_sale DECIMAL(12,3) NOT NULL DEFAULT 1,
  price DECIMAL(14,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  tax_inclusive TINYINT(1) NOT NULL DEFAULT 1,
  cart_enabled TINYINT(1) NOT NULL DEFAULT 1,
  min_qty DECIMAL(12,3) NOT NULL DEFAULT 1,
  max_qty DECIMAL(12,3) NULL,
  sale_unit_label VARCHAR(60) NOT NULL DEFAULT 'pack',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_variants_sku (sku),
  INDEX idx_product_variants_product (product_id,cart_enabled,sort_order),
  CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_related_products (
  product_id BIGINT UNSIGNED NOT NULL,
  related_product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id,related_product_id),
  INDEX idx_related_products_target (related_product_id),
  CONSTRAINT fk_related_products_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_related_products_related FOREIGN KEY (related_product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_item_options (
  order_item_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  variant_sku VARCHAR(70) NULL,
  variant_label VARCHAR(120) NULL,
  units_per_sale DECIMAL(12,3) NOT NULL DEFAULT 1,
  inventory_quantity DECIMAL(12,3) NOT NULL,
  PRIMARY KEY (order_item_id),
  INDEX idx_sales_order_item_options_variant (variant_id),
  CONSTRAINT fk_sales_order_item_options_item FOREIGN KEY (order_item_id) REFERENCES sales_order_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_sales_order_item_options_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_account_security (
  customer_id BIGINT UNSIGNED NOT NULL,
  email_verified_at DATETIME NULL,
  verification_sent_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  CONSTRAINT fk_customer_account_security_customer FOREIGN KEY (customer_id) REFERENCES customer_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO customer_account_security (customer_id,email_verified_at)
SELECT id,NOW() FROM customer_accounts;

CREATE TABLE IF NOT EXISTS customer_security_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  token_type VARCHAR(30) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  request_ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_security_token_hash (token_hash),
  INDEX idx_customer_security_tokens_customer (customer_id,token_type,created_at),
  INDEX idx_customer_security_tokens_expiry (token_type,expires_at,used_at),
  CONSTRAINT fk_customer_security_tokens_customer FOREIGN KEY (customer_id) REFERENCES customer_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id VARCHAR(80) NULL,
  summary VARCHAR(500) NULL,
  metadata_json MEDIUMTEXT NULL,
  ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_admin_audit_created (created_at),
  INDEX idx_admin_audit_entity (entity_type,entity_id,created_at),
  INDEX idx_admin_audit_admin (admin_user_id,created_at),
  CONSTRAINT fk_admin_audit_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
