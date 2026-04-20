-- Migration 005: Update violation types to Minor Offense and Major Offense categories
-- This migration removes the old violation categorization (Minor, Moderate, Major)
-- and replaces it with: Minor Offense and Major Offense (Categories A, B, C, D)

-- Step 1: Delete all existing violations since they reference old violation types
-- This ensures a clean transition to the new categorization
DELETE FROM violations;

-- Step 2: Delete old violation types
DELETE FROM violation_types WHERE violation_type_id IN (1, 2, 3, 4, 5, 6);

-- Step 3: Insert new violation types with updated categorization
INSERT INTO violation_types (violation_type_id, type_name, severity_level, is_active)
VALUES
    (1, 'Minor Offense', 'minor', true),
    (2, 'Major Offense - Category A', 'major', true),
    (3, 'Major Offense - Category B', 'major', true),
    (4, 'Major Offense - Category C', 'major', true),
    (5, 'Major Offense - Category D', 'major', true)
ON CONFLICT (violation_type_id) DO UPDATE
SET type_name = excluded.type_name,
    severity_level = excluded.severity_level,
    is_active = excluded.is_active;

-- Step 4: Reset the sequence for violation_types
SELECT setval(pg_get_serial_sequence('violation_types', 'violation_type_id'),
              (SELECT COALESCE(MAX(violation_type_id), 5) FROM violation_types),
              true);

-- Migration Notes:
-- - Old severity levels: 'minor', 'moderate', 'major'
-- - New severity levels: 'minor' (Minor Offense), 'major' (Major Offense - Categories A-D)
-- - The 'moderate' severity level is no longer used
-- - All violations have been deleted and must be re-entered with new categorization
-- - Dashboard charts will now only show 'minor' and 'major' categories
