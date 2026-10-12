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
SELECT '2026_10_13_000001_rename_pass_outcomes_to_passed', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_000001_rename_pass_outcomes_to_passed');
