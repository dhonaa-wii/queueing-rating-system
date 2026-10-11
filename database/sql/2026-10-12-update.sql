-- ARPQRS database update — 2026-10-11 / 2026-10-12 releases
--
-- For servers without a terminal (no `php artisan migrate`). Run in phpMyAdmin
-- (select the app's database → SQL, or Import). Run it BEFORE uploading the new
-- code: the old code works with these changes, the new code needs them.
--
-- Safe to run more than once: every step skips itself if already done.
--
-- Applies the same changes as this migration and records it in the
-- `migrations` table, so a later `php artisan migrate` won't run it again:
--   2026_10_11_000001_add_student_panelist_choice
--
-- Expects the last row of `migrations` to be
-- 2026_10_04_000001_add_evaluation_form_to_category_research_tracks.
-- If it is older, run 2026-10-04-update.sql first.

-- Category setting: groups choose their panelists at registration
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presentation_categories' AND COLUMN_NAME = 'students_choose_panelists') = 0,
    'ALTER TABLE `presentation_categories` ADD `students_choose_panelists` TINYINT(1) NOT NULL DEFAULT 0 AFTER `panelist_count`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- The panelists a group picked, in order (first = Chair)
CREATE TABLE IF NOT EXISTS `research_group_panelist_choices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `research_group_id` BIGINT UNSIGNED NOT NULL,
  `panelist_user_id` BIGINT UNSIGNED NOT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rg_panelist_choices_unique` (`research_group_id`, `panelist_user_id`),
  KEY `research_group_panelist_choices_panelist_user_id_foreign` (`panelist_user_id`),
  CONSTRAINT `research_group_panelist_choices_panelist_user_id_foreign` FOREIGN KEY (`panelist_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `research_group_panelist_choices_research_group_id_foreign` FOREIGN KEY (`research_group_id`) REFERENCES `research_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record the migration as run (only once)
SET @batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_11_000001_add_student_panelist_choice', @batch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_11_000001_add_student_panelist_choice');
