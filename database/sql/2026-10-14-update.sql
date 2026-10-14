CREATE TABLE IF NOT EXISTS `backup_20261014_submission_remarks` AS
SELECT `id`, `presentation_outcome_id` FROM `evaluation_submissions`;

CREATE TABLE IF NOT EXISTS `backup_20261014_attempt_outcomes` AS
SELECT `id`, `final_outcome_id` FROM `presentation_attempts`;

UPDATE `evaluation_submissions` s
JOIN `submission_statuses` st ON st.id = s.submission_status_id AND st.code IN ('SUBMITTED', 'FINALIZED')
JOIN `presentation_outcomes` o ON o.code = CASE
        WHEN ROUND(ROUND(s.weighted_total_score, 2)) >= 91 THEN 'PASS_NO_REVISION'
        WHEN ROUND(ROUND(s.weighted_total_score, 2)) >= 81 THEN 'PASS_MINOR_REVISION'
        WHEN ROUND(ROUND(s.weighted_total_score, 2)) >= 71 THEN 'PASS_MAJOR_REVISION'
        WHEN ROUND(ROUND(s.weighted_total_score, 2)) >= 61 THEN 'RE_DEFENSE'
        ELSE 'FAILED' END
SET s.presentation_outcome_id = o.id
WHERE s.weighted_total_score IS NOT NULL
  AND s.evaluation_form_version_id NOT IN (
      SELECT m.evaluation_form_version_id
      FROM `evaluation_form_version_presentation_modes` m
      JOIN `presentation_modes` pm ON pm.id = m.presentation_mode_id
      WHERE pm.code = 'TITLE_PROPOSAL');

UPDATE `presentation_attempts` a
JOIN `presentation_statuses` ps ON ps.id = a.presentation_status_id AND ps.code = 'COMPLETED'
JOIN (
    SELECT s.presentation_attempt_id AS attempt_id, ROUND(AVG(s.weighted_total_score), 2) AS grade
    FROM `evaluation_submissions` s
    JOIN `submission_statuses` st ON st.id = s.submission_status_id AND st.code IN ('SUBMITTED', 'FINALIZED')
    WHERE s.weighted_total_score IS NOT NULL
      AND s.evaluation_form_version_id NOT IN (
          SELECT m.evaluation_form_version_id
          FROM `evaluation_form_version_presentation_modes` m
          JOIN `presentation_modes` pm ON pm.id = m.presentation_mode_id
          WHERE pm.code = 'TITLE_PROPOSAL')
    GROUP BY s.presentation_attempt_id
) g ON g.attempt_id = a.id
JOIN `presentation_outcomes` o ON o.code = CASE
        WHEN ROUND(g.grade) >= 91 THEN 'PASS_NO_REVISION'
        WHEN ROUND(g.grade) >= 81 THEN 'PASS_MINOR_REVISION'
        WHEN ROUND(g.grade) >= 71 THEN 'PASS_MAJOR_REVISION'
        WHEN ROUND(g.grade) >= 61 THEN 'RE_DEFENSE'
        ELSE 'FAILED' END
LEFT JOIN `presentation_attempts` nxt ON nxt.previous_attempt_id = a.id
SET a.final_outcome_id = o.id
WHERE nxt.id IS NULL;

SET @batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_14_000001_regrade_existing_remarks_with_rounding', @batch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_14_000001_regrade_existing_remarks_with_rounding');
