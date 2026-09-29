SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='supplier_outreach_campaigns' AND COLUMN_NAME='subject_template');
SET @sql = IF(@col_exists=0, 'ALTER TABLE supplier_outreach_campaigns ADD COLUMN subject_template VARCHAR(220) NULL AFTER template_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='supplier_outreach_campaigns' AND COLUMN_NAME='body_html');
SET @sql = IF(@col_exists=0, 'ALTER TABLE supplier_outreach_campaigns ADD COLUMN body_html MEDIUMTEXT NULL AFTER subject_template', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE supplier_outreach_campaigns c
JOIN supplier_outreach_templates t ON t.id=c.template_id
SET c.subject_template=COALESCE(NULLIF(c.subject_template,''),t.subject_template),
    c.body_html=COALESCE(NULLIF(c.body_html,''),t.body_html)
WHERE c.subject_template IS NULL OR c.subject_template='' OR c.body_html IS NULL OR c.body_html='';
