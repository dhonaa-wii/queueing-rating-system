-- 2026-10-21 — Removed rooms move their groups, groups queued before any room, re-defense always last, FIFO default.
-- Same as migrations 2026_10_21_000001_add_room_removed_and_redefense_reasons_and_default_fifo
-- and 2026_10_21_000002_make_attempt_schedule_room_nullable.
-- Run once in phpMyAdmin on the hosted database (after 2026-10-19-update.sql).
-- Safe to run twice. Groups already stuck in a removed room are moved
-- automatically on the next Admin page load after the new code is deployed.

INSERT INTO `adjustment_reasons` (`code`, `name`, `is_active`)
VALUES ('ROOM_REMOVED', 'Room Removed From Day', 0),
       ('RE_DEFENSE_LAST', 'Re-Defense Kept Last', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `is_active` = 0;

-- Every category that never saved a queue strategy gets FIFO.
INSERT INTO `category_queue_settings`
    (`category_id`, `queue_strategy_id`, `called_waiting_minutes`, `allow_same_day_reinsertion`,
     `late_defer_enabled`, `unresolved_absent_end_of_day`, `settings_json`, `created_at`, `updated_at`)
SELECT pc.id, qs.id, 5, 1, 1, 1, NULL, NOW(), NOW()
FROM `presentation_categories` pc
JOIN `queue_strategies` qs ON qs.code = 'FIFO'
WHERE NOT EXISTS (SELECT 1 FROM `category_queue_settings` cqs WHERE cqs.category_id = pc.id);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_21_000001_add_room_removed_and_redefense_reasons_and_default_fifo', x.next_batch
FROM (SELECT COALESCE(MAX(`batch`), 0) + 1 AS next_batch FROM `migrations`) x
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_21_000001_add_room_removed_and_redefense_reasons_and_default_fifo');

-- A group can be in the queue before any room exists ("to be scheduled"):
-- the room on a queue placement becomes optional.
-- Same as migration 2026_10_21_000002_make_attempt_schedule_room_nullable.
ALTER TABLE `attempt_schedules` MODIFY `presentation_date_room_id` BIGINT UNSIGNED NULL;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_21_000002_make_attempt_schedule_room_nullable', x.next_batch
FROM (SELECT COALESCE(MAX(`batch`), 0) AS next_batch FROM `migrations`) x
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_21_000002_make_attempt_schedule_room_nullable');
