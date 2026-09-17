CREATE TABLE IF NOT EXISTS enquiries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  topic VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(180) NOT NULL,
  company VARCHAR(150) NULL,
  message TEXT NOT NULL,
  ip_hash CHAR(64) NULL,
  PRIMARY KEY (id),
  INDEX idx_enquiries_submitted_at (submitted_at),
  INDEX idx_enquiries_topic (topic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
