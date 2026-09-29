SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers' AND COLUMN_NAME='outreach_email_enabled');
SET @sql = IF(@col_exists=0, 'ALTER TABLE suppliers ADD COLUMN outreach_email_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER email', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers' AND COLUMN_NAME='outreach_last_emailed_at');
SET @sql = IF(@col_exists=0, 'ALTER TABLE suppliers ADD COLUMN outreach_last_emailed_at DATETIME NULL AFTER outreach_email_enabled', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suppliers' AND COLUMN_NAME='outreach_opt_out_reason');
SET @sql = IF(@col_exists=0, 'ALTER TABLE suppliers ADD COLUMN outreach_opt_out_reason VARCHAR(255) NULL AFTER outreach_last_emailed_at', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS site_branding (
  id TINYINT UNSIGNED NOT NULL,
  site_name VARCHAR(120) NOT NULL DEFAULT 'Moleqra',
  logo_path VARCHAR(255) NULL,
  primary_color CHAR(7) NOT NULL DEFAULT '#63e6be',
  secondary_color CHAR(7) NOT NULL DEFAULT '#8be9fd',
  background_color CHAR(7) NOT NULL DEFAULT '#070b14',
  surface_color CHAR(7) NOT NULL DEFAULT '#0e1526',
  surface_alt_color CHAR(7) NOT NULL DEFAULT '#141d31',
  text_color CHAR(7) NOT NULL DEFAULT '#f4f7fb',
  muted_color CHAR(7) NOT NULL DEFAULT '#9aa8bd',
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_site_branding_admin FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_branding (id,site_name,logo_path) VALUES (1,'Moleqra',NULL)
ON DUPLICATE KEY UPDATE id=VALUES(id);

CREATE TABLE IF NOT EXISTS site_seo_settings (
  id TINYINT UNSIGNED NOT NULL,
  title_suffix VARCHAR(120) NOT NULL DEFAULT 'Moleqra',
  default_description VARCHAR(300) NOT NULL DEFAULT 'Moleqra provides research-use-only materials with batch documentation and transparent quality controls.',
  organization_name VARCHAR(160) NOT NULL DEFAULT 'Moleqra Biosciences',
  allow_indexing TINYINT(1) NOT NULL DEFAULT 0,
  sitemap_enabled TINYINT(1) NOT NULL DEFAULT 1,
  extra_robots_disallow TEXT NULL,
  og_image_path VARCHAR(255) NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_site_seo_admin FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_seo_settings (id) VALUES (1)
ON DUPLICATE KEY UPDATE id=VALUES(id);

CREATE TABLE IF NOT EXISTS supplier_outreach_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(140) NOT NULL,
  subject_template VARCHAR(220) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_supplier_outreach_templates_active (is_active,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_outreach_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Draft',
  target_supplier_status VARCHAR(40) NULL DEFAULT 'Prospect',
  target_region VARCHAR(80) NULL,
  daily_limit SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  min_days_between_contacts SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  follow_up_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  scheduled_start DATETIME NULL,
  last_processed_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_supplier_outreach_campaigns_status (status,scheduled_start),
  CONSTRAINT fk_supplier_outreach_campaign_template FOREIGN KEY (template_id) REFERENCES supplier_outreach_templates(id) ON DELETE RESTRICT,
  CONSTRAINT fk_supplier_outreach_campaign_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_outreach_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  supplier_id BIGINT UNSIGNED NOT NULL,
  recipient VARCHAR(180) NOT NULL,
  subject VARCHAR(220) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Pending',
  scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_supplier_outreach_queue_campaign_supplier (campaign_id,supplier_id),
  INDEX idx_supplier_outreach_queue_ready (status,scheduled_at),
  CONSTRAINT fk_supplier_outreach_queue_campaign FOREIGN KEY (campaign_id) REFERENCES supplier_outreach_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_supplier_outreach_queue_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  status_label VARCHAR(80) NOT NULL,
  customer_message VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_order_status_events_order (order_id,created_at),
  CONSTRAINT fk_order_status_events_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_status_events_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO supplier_outreach_templates (name,subject_template,body_html,is_active)
SELECT
  'Formal first contact',
  'Retail / Reseller Partnership Enquiry – {{site_name}}',
  '<p>Dear {{contact_greeting}},</p><p>My name is Ben, and I am contacting you on behalf of <strong>{{site_name}}</strong>, a South African research-materials business focused on clearly documented, research-use-only products with an emphasis on batch traceability, Certificates of Analysis (COAs), analytical documentation and responsible product presentation.</p><p>We are currently evaluating suppliers for our initial product range and would like to enquire whether you permit approved third parties to resell or retail your products.</p><p>We would be interested in discussing a potential <strong>reseller, wholesale, or private-label relationship</strong> with your company.</p><p>Could you please advise on the following:</p><ul><li>Whether {{site_name}} may purchase your products for resale</li><li>Wholesale or reseller pricing</li><li>Minimum order quantities or minimum order values</li><li>Batch-specific COAs</li><li>Available HPLC and/or mass-spectrometry documentation</li><li>Authorised reseller branding and product-image permissions</li><li>Private-label availability</li><li>Typical lead times and stock availability</li><li>Shipping options within South Africa</li><li>Payment terms</li><li>Damaged, non-conforming, or incorrectly supplied product procedures</li></ul><p>Where relevant, we would appreciate a current wholesale price list, reseller application form, product catalogue and reseller requirements.</p><p>We are currently completing supplier onboarding ahead of launch and are interested in establishing long-term relationships with reliable suppliers.</p><p>Thank you, and I look forward to hearing from you.</p><p>Kind regards,<br><strong>Ben Henning</strong><br><strong>{{site_name}}</strong><br>{{contact_email}}<br>{{website}}</p><p style="font-size:12px;color:#65717e">If you do not wish to receive further partnership outreach from {{site_name}}, please reply and let us know.</p>',
  1
WHERE NOT EXISTS (SELECT 1 FROM supplier_outreach_templates WHERE name='Formal first contact');
