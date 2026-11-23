-- Apply to EXISTING chatapp database AFTER DATABASE_MIGRATION_2026_11_04.sql.
-- BACK UP the current database first. Does not delete or reset existing data.
USE `chatapp`;

-- Usernames are optional for previously created accounts (they retain email login).
-- Username-only newly provisioned participant accounts do not need a fabricated email.
SET @has_username := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='username');
SET @ddl := IF(@has_username=0,'ALTER TABLE `users` ADD COLUMN `username` VARCHAR(30) NULL AFTER `lname`','SELECT 1');
PREPARE stmt FROM @ddl;EXECUTE stmt;DEALLOCATE PREPARE stmt;
ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(190) NULL;
SET @username_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='users' AND index_name='users_username_unique');
SET @ddl := IF(@username_idx=0,'ALTER TABLE `users` ADD UNIQUE KEY `users_username_unique` (`username`)','SELECT 1');
PREPARE stmt FROM @ddl;EXECUTE stmt;DEALLOCATE PREPARE stmt;

-- An immutable approved list can have append-only supplementary batches.
SET @has_parent := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='registry_batches' AND column_name='parent_batch_id');
SET @ddl := IF(@has_parent=0,'ALTER TABLE `registry_batches` ADD COLUMN `parent_batch_id` BIGINT UNSIGNED NULL AFTER `created_by_admin_id`','SELECT 1');
PREPARE stmt FROM @ddl;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @parent_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='registry_batches' AND index_name='registry_batches_parent');
SET @ddl := IF(@parent_idx=0,'ALTER TABLE `registry_batches` ADD KEY `registry_batches_parent` (`parent_batch_id`)','SELECT 1');
PREPARE stmt FROM @ddl;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @parent_fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='registry_batches' AND constraint_name='registry_batches_parent_fk' AND constraint_type='FOREIGN KEY');
SET @ddl := IF(@parent_fk=0,'ALTER TABLE `registry_batches` ADD CONSTRAINT `registry_batches_parent_fk` FOREIGN KEY (`parent_batch_id`) REFERENCES `registry_batches` (`id`) ON DELETE RESTRICT','SELECT 1');
PREPARE stmt FROM @ddl;EXECUTE stmt;DEALLOCATE PREPARE stmt;
