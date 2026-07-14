-- =========================================================================
-- MUTABAKAT ek alanları (§5): mutabakat türü, dönem başlangıç/bitiş, ek not,
-- onaylayan kullanıcı. Idempotent (kolon varsa atlar). Fresh kurulum install.sql.
-- =========================================================================
SET NAMES utf8mb4;

SET @add := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE reconciliations ADD COLUMN recon_type VARCHAR(20) NOT NULL DEFAULT 'cari' AFTER cari_code", "DO 0")
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reconciliations' AND COLUMN_NAME='recon_type');
PREPARE s FROM @add; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE reconciliations ADD COLUMN period_start DATE NULL AFTER period", "DO 0")
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reconciliations' AND COLUMN_NAME='period_start');
PREPARE s FROM @add; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE reconciliations ADD COLUMN period_end DATE NULL AFTER period_start", "DO 0")
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reconciliations' AND COLUMN_NAME='period_end');
PREPARE s FROM @add; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE reconciliations ADD COLUMN extra_note TEXT NULL AFTER description", "DO 0")
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reconciliations' AND COLUMN_NAME='extra_note');
PREPARE s FROM @add; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (SELECT IF(COUNT(*)=0,
  "ALTER TABLE reconciliations ADD COLUMN approved_by INT UNSIGNED NULL AFTER prepared_by", "DO 0")
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reconciliations' AND COLUMN_NAME='approved_by');
PREPARE s FROM @add; EXECUTE s; DEALLOCATE PREPARE s;
