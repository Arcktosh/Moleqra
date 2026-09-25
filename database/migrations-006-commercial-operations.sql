CREATE TABLE IF NOT EXISTS commerce_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  notification_type VARCHAR(60) NOT NULL,
  recipient VARCHAR(180) NOT NULL,
  subject VARCHAR(220) NOT NULL,
  transport VARCHAR(40) NOT NULL DEFAULT 'log',
  status VARCHAR(30) NOT NULL DEFAULT 'Pending',
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_commerce_notifications_order (order_id,created_at),
  INDEX idx_commerce_notifications_customer (customer_id,created_at),
  INDEX idx_commerce_notifications_status (status,created_at),
  CONSTRAINT fk_commerce_notifications_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_commerce_notifications_customer FOREIGN KEY (customer_id) REFERENCES customer_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  invoice_number VARCHAR(50) NOT NULL,
  currency CHAR(3) NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL,
  shipping_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_invoices_order (order_id),
  UNIQUE KEY uq_sales_invoices_number (invoice_number),
  CONSTRAINT fk_sales_invoices_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'ZA',
  province VARCHAR(120) NULL,
  min_order DECIMAL(14,2) NOT NULL DEFAULT 0,
  max_order DECIMAL(14,2) NULL,
  rate DECIMAL(14,2) NOT NULL DEFAULT 0,
  free_threshold DECIMAL(14,2) NULL,
  priority INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_shipping_rules_match (is_active,country_code,province,priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shipping_rules (name,country_code,province,min_order,max_order,rate,free_threshold,priority,is_active)
SELECT 'South Africa standard','ZA',NULL,0,NULL,120.00,3500.00,100,1
WHERE NOT EXISTS (SELECT 1 FROM shipping_rules);

CREATE TABLE IF NOT EXISTS order_shipping_quotes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  shipping_rule_id BIGINT UNSIGNED NULL,
  rule_name VARCHAR(120) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  country_code CHAR(2) NOT NULL,
  province VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_shipping_quotes_order (order_id),
  INDEX idx_order_shipping_quotes_rule (shipping_rule_id),
  CONSTRAINT fk_order_shipping_quotes_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_shipping_quotes_rule FOREIGN KEY (shipping_rule_id) REFERENCES shipping_rules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_refunds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  payment_transaction_id BIGINT UNSIGNED NULL,
  refund_number VARCHAR(50) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  currency CHAR(3) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Requested',
  gateway_reference VARCHAR(160) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_refunds_number (refund_number),
  INDEX idx_order_refunds_order (order_id,status),
  INDEX idx_order_refunds_payment (payment_transaction_id),
  CONSTRAINT fk_order_refunds_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_refunds_payment FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions(id) ON DELETE SET NULL,
  CONSTRAINT fk_order_refunds_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_reconciliations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  payment_transaction_id BIGINT UNSIGNED NOT NULL,
  reconciliation_status VARCHAR(30) NOT NULL DEFAULT 'Matched',
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_reconciliation_transaction (payment_transaction_id),
  INDEX idx_payment_reconciliation_order (order_id,created_at),
  CONSTRAINT fk_payment_reconciliation_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_reconciliation_transaction FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_reconciliation_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
