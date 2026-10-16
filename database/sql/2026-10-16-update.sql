-- 2026-10-16 — Security Settings (login lockout, password age).
-- Same as migration 2026_10_16_000001_add_security_policy_columns_to_users.
-- Run once in phpMyAdmin on the hosted database. Adds no settings: the
-- Super Admin sets them on Security Settings.

ALTER TABLE `users`
    ADD COLUMN `failed_login_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_login_at`,
    ADD COLUMN `locked_until` TIMESTAMP NULL DEFAULT NULL AFTER `failed_login_attempts`,
    ADD COLUMN `password_changed_at` TIMESTAMP NULL DEFAULT NULL AFTER `locked_until`;

-- The password age clock starts now for everyone.
UPDATE `users` SET `password_changed_at` = NOW();

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_16_000001_add_security_policy_columns_to_users', COALESCE(MAX(`batch`), 0) + 1
FROM `migrations`;
