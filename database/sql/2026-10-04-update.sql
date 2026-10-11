-- ARPQRS database update — 2026-10-03 / 2026-10-04 releases
--
-- For servers without a terminal (no `php artisan migrate`). Run in phpMyAdmin
-- (select the app's database → SQL, or Import). Run it BEFORE uploading the new
-- code: the old code works with these changes, the new code needs them.
--
-- Safe to run more than once: every step skips itself if already done.
--
-- Applies the same changes as these four migrations and records them in the
-- `migrations` table, so a later `php artisan migrate` won't run them again:
--   2026_10_03_000001_rename_planned_event_date_status_display_name
--   2026_10_03_000002_remove_priority_based_queue_strategy
--   2026_10_03_000003_add_college_id_to_evaluation_forms
--   2026_10_04_000001_add_evaluation_form_to_category_research_tracks
--
-- Expects the last row of `migrations` to be 2026_09_30_000002_add_defer_reasons.

-- 2026_10_03_000001 — "Planned" days read "Upcoming"
UPDATE `event_date_statuses` SET `name` = 'Upcoming' WHERE `code` = 'PLANNED';

-- 2026_10_03_000002 — Priority Based strategy removed (kept if a category still uses it)
DELETE FROM `queue_strategies`
WHERE `code` = 'PRIORITY_BASED'
  AND `id` NOT IN (SELECT `queue_strategy_id` FROM `category_queue_settings`);

-- 2026_10_03_000003 — evaluation forms belong to a college
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluation_forms' AND COLUMN_NAME = 'college_id') = 0,
    'ALTER TABLE `evaluation_forms` ADD `college_id` BIGINT UNSIGNED NULL AFTER `id`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluation_forms'
       AND CONSTRAINT_NAME = 'evaluation_forms_college_id_foreign') = 0,
    'ALTER TABLE `evaluation_forms` ADD CONSTRAINT `evaluation_forms_college_id_foreign` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE evaluation_forms f
JOIN administrator_profiles ap ON ap.user_id = f.created_by
SET f.college_id = ap.college_id
WHERE f.college_id IS NULL;

UPDATE evaluation_forms f
JOIN (SELECT v.evaluation_form_id AS form_id, MIN(c.college_id) AS college_id
      FROM category_evaluation_forms cef
      JOIN evaluation_form_versions v ON v.id = cef.evaluation_form_version_id
      JOIN presentation_categories c ON c.id = cef.category_id
      WHERE c.college_id IS NOT NULL
      GROUP BY v.evaluation_form_id) x ON x.form_id = f.id
SET f.college_id = x.college_id
WHERE f.college_id IS NULL;

UPDATE `evaluation_forms`
SET `college_id` = (SELECT MIN(`id`) FROM `colleges`)
WHERE `college_id` IS NULL;

-- 2026_10_04_000001 — each track's own evaluation form
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'category_research_tracks' AND COLUMN_NAME = 'evaluation_form_version_id') = 0,
    'ALTER TABLE `category_research_tracks` ADD `evaluation_form_version_id` BIGINT UNSIGNED NULL AFTER `name`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'category_research_tracks'
       AND CONSTRAINT_NAME = 'category_research_tracks_evaluation_form_version_id_foreign') = 0,
    'ALTER TABLE `category_research_tracks` ADD CONSTRAINT `category_research_tracks_evaluation_form_version_id_foreign` FOREIGN KEY (`evaluation_form_version_id`) REFERENCES `evaluation_form_versions` (`id`) ON DELETE SET NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Record the four migrations as run (each only once)
SET @batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.name, @batch
FROM (
    SELECT '2026_10_03_000001_rename_planned_event_date_status_display_name' AS name
    UNION ALL SELECT '2026_10_03_000002_remove_priority_based_queue_strategy'
    UNION ALL SELECT '2026_10_03_000003_add_college_id_to_evaluation_forms'
    UNION ALL SELECT '2026_10_04_000001_add_evaluation_form_to_category_research_tracks'
) m
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.name);
