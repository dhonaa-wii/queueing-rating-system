UPDATE `event_date_statuses` SET `name` = 'Upcoming' WHERE `code` = 'PLANNED';

DELETE FROM `queue_strategies`
WHERE `code` = 'PRIORITY_BASED'
  AND `id` NOT IN (SELECT `queue_strategy_id` FROM `category_queue_settings` WHERE `queue_strategy_id` IS NOT NULL);

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

UPDATE `evaluation_forms` f
JOIN `administrator_profiles` ap ON ap.user_id = f.created_by
SET f.college_id = ap.college_id
WHERE f.college_id IS NULL;

UPDATE `evaluation_forms` f
JOIN (SELECT v.evaluation_form_id AS form_id, MIN(c.college_id) AS college_id
      FROM `category_evaluation_forms` cef
      JOIN `evaluation_form_versions` v ON v.id = cef.evaluation_form_version_id
      JOIN `presentation_categories` c ON c.id = cef.category_id
      WHERE c.college_id IS NOT NULL
      GROUP BY v.evaluation_form_id) x ON x.form_id = f.id
SET f.college_id = x.college_id
WHERE f.college_id IS NULL;

UPDATE `evaluation_forms`
SET `college_id` = (SELECT MIN(`id`) FROM `colleges`)
WHERE `college_id` IS NULL;

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

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presentation_categories' AND COLUMN_NAME = 'students_choose_panelists') = 0,
    'ALTER TABLE `presentation_categories` ADD `students_choose_panelists` TINYINT(1) NOT NULL DEFAULT 0 AFTER `panelist_count`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'research_group_panelist_choices') = 0,
    'CREATE TABLE `research_group_panelist_choices` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `presentation_outcomes` SET `name` = 'Passed with No Revision' WHERE `code` = 'PASS_NO_REVISION';
UPDATE `presentation_outcomes` SET `name` = 'Passed with Minor Revision' WHERE `code` = 'PASS_MINOR_REVISION';
UPDATE `presentation_outcomes` SET `name` = 'Passed with Major Revision' WHERE `code` = 'PASS_MAJOR_REVISION';

UPDATE `evaluation_form_version_outcomes` vo
JOIN `presentation_outcomes` po ON po.id = vo.presentation_outcome_id
SET vo.sort_order = CASE po.code
    WHEN 'PASS_NO_REVISION' THEN 1
    WHEN 'PASS_MINOR_REVISION' THEN 2
    WHEN 'PASS_MAJOR_REVISION' THEN 3
    WHEN 'RE_DEFENSE' THEN 4
    WHEN 'FAILED' THEN 5
    ELSE 6 END;

SET @batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.name, @batch
FROM (
    SELECT '2026_10_03_000001_rename_planned_event_date_status_display_name' AS name
    UNION ALL SELECT '2026_10_03_000002_remove_priority_based_queue_strategy'
    UNION ALL SELECT '2026_10_03_000003_add_college_id_to_evaluation_forms'
    UNION ALL SELECT '2026_10_04_000001_add_evaluation_form_to_category_research_tracks'
    UNION ALL SELECT '2026_10_11_000001_add_student_panelist_choice'
    UNION ALL SELECT '2026_10_13_000001_rename_pass_outcomes_to_passed'
) m
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.name);
