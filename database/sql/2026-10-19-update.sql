-- 2026-10-19 — External panelists + adviser account link.
-- Same as migrations 2026_10_19_000001_add_external_department_to_panelist_profiles
-- and 2026_10_19_000002_add_technical_adviser_user_id_to_research_groups.
-- Run once in phpMyAdmin on the hosted database (after 2026-10-16-update.sql).
-- Works on MySQL 5.7+/8 and MariaDB 10.x (no REGEXP_REPLACE).

ALTER TABLE `panelist_profiles`
    ADD COLUMN `is_external` TINYINT(1) NOT NULL DEFAULT 0 AFTER `college_id`,
    ADD COLUMN `external_department` VARCHAR(150) NULL DEFAULT NULL AFTER `is_external`;

ALTER TABLE `research_groups`
    ADD COLUMN `technical_adviser_user_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `technical_adviser_name`,
    ADD CONSTRAINT `research_groups_technical_adviser_user_id_foreign`
        FOREIGN KEY (`technical_adviser_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Link existing groups to their adviser's account: the adviser name matches
-- exactly one non-deleted panelist of the category's college (first + last,
-- either order, case and extra spaces ignored).
UPDATE `research_groups` rg
JOIN `presentation_categories` pc ON pc.id = rg.category_id
JOIN (
    SELECT rg2.id AS group_id, MIN(pp.user_id) AS user_id, COUNT(*) AS matches
    FROM `research_groups` rg2
    JOIN `presentation_categories` pc2 ON pc2.id = rg2.category_id
    JOIN `panelist_profiles` pp ON pp.college_id = pc2.college_id
    JOIN `users` u ON u.id = pp.user_id AND u.deleted_at IS NULL
    JOIN `user_profiles` up ON up.user_id = pp.user_id
    WHERE rg2.technical_adviser_name IS NOT NULL
      AND LOWER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(rg2.technical_adviser_name, '  ', ' '), '  ', ' '), '  ', ' '), '  ', ' '))) IN (
          LOWER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(up.first_name, ' ', up.last_name), '  ', ' '), '  ', ' '), '  ', ' '), '  ', ' '))),
          LOWER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(up.last_name, ' ', up.first_name), '  ', ' '), '  ', ' '), '  ', ' '), '  ', ' ')))
      )
    GROUP BY rg2.id
) m ON m.group_id = rg.id AND m.matches = 1
SET rg.technical_adviser_user_id = m.user_id;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_19_000001_add_external_department_to_panelist_profiles', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_19_000002_add_technical_adviser_user_id_to_research_groups', MAX(`batch`) FROM `migrations`;
