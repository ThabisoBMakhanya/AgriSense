-- Classify shared farm records into the two farm journal views.
ALTER TABLE farm_tasks
    ADD COLUMN IF NOT EXISTS record_scope VARCHAR(20) NOT NULL DEFAULT 'crops';
ALTER TABLE farm_records
    ADD COLUMN IF NOT EXISTS record_scope VARCHAR(20) NOT NULL DEFAULT 'crops';
ALTER TABLE field_cases
    ADD COLUMN IF NOT EXISTS subject_type VARCHAR(20) NOT NULL DEFAULT 'crops';

UPDATE farm_tasks
SET record_scope = 'animals'
WHERE LOWER(category) IN ('animal health', 'feed and water') AND record_scope = 'crops';
