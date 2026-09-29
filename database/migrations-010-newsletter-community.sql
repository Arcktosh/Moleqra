CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NULL,
  email VARCHAR(180) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'Pending',
  source VARCHAR(80) NOT NULL DEFAULT 'Website',
  confirm_token_hash CHAR(64) NULL,
  confirm_expires_at DATETIME NULL,
  unsubscribe_token_hash CHAR(64) NULL,
  confirmed_at DATETIME NULL,
  unsubscribed_at DATETIME NULL,
  last_sent_at DATETIME NULL,
  consent_ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_subscriber_email (email),
  UNIQUE KEY uq_newsletter_confirm_token (confirm_token_hash),
  UNIQUE KEY uq_newsletter_unsubscribe_token (unsubscribe_token_hash),
  INDEX idx_newsletter_subscribers_status (status,created_at),
  INDEX idx_newsletter_subscribers_customer (customer_id),
  CONSTRAINT fk_newsletter_subscriber_customer FOREIGN KEY (customer_id) REFERENCES customer_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  subject_template VARCHAR(220) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Draft',
  scheduled_at DATETIME NULL,
  batch_limit SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  last_processed_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_newsletter_campaigns_status (status,scheduled_at),
  CONSTRAINT fk_newsletter_campaign_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  subscriber_id BIGINT UNSIGNED NOT NULL,
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
  UNIQUE KEY uq_newsletter_queue_campaign_subscriber (campaign_id,subscriber_id),
  INDEX idx_newsletter_queue_ready (status,scheduled_at),
  CONSTRAINT fk_newsletter_queue_campaign FOREIGN KEY (campaign_id) REFERENCES newsletter_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_newsletter_queue_subscriber FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forum_customer_moderation (
  customer_id BIGINT UNSIGNED NOT NULL,
  forum_status VARCHAR(24) NOT NULL DEFAULT 'Active',
  spam_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  block_reason VARCHAR(255) NULL,
  blocked_at DATETIME NULL,
  blocked_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  INDEX idx_forum_customer_status (forum_status,spam_score),
  CONSTRAINT fk_forum_moderation_customer FOREIGN KEY (customer_id) REFERENCES customer_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_moderation_admin FOREIGN KEY (blocked_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forum_topics (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NULL,
  created_by_customer_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'Open',
  is_product_topic TINYINT(1) NOT NULL DEFAULT 0,
  last_post_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_forum_topic_slug (slug),
  UNIQUE KEY uq_forum_product_topic (product_id),
  INDEX idx_forum_topics_status (status,last_post_at,created_at),
  INDEX idx_forum_topics_customer (created_by_customer_id,created_at),
  CONSTRAINT fk_forum_topic_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_topic_customer FOREIGN KEY (created_by_customer_id) REFERENCES customer_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forum_posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'Published',
  hidden_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_forum_posts_topic (topic_id,status,created_at),
  INDEX idx_forum_posts_customer (customer_id,created_at),
  CONSTRAINT fk_forum_post_topic FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_post_customer FOREIGN KEY (customer_id) REFERENCES customer_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
