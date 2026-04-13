-- Migration: appointment_notes table and schema updates for appointments
-- Run this script once against your SQL Server database.

-- 1. Add officer_id column to appointments if it does not already exist
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'dbo'
      AND TABLE_NAME   = 'appointments'
      AND COLUMN_NAME  = 'officer_id'
)
BEGIN
    ALTER TABLE dbo.appointments ADD officer_id INT NULL;
END
GO

-- 2. Add updated_at column to appointments if it does not already exist
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'dbo'
      AND TABLE_NAME   = 'appointments'
      AND COLUMN_NAME  = 'updated_at'
)
BEGIN
    ALTER TABLE dbo.appointments ADD updated_at DATETIME NULL;
END
GO

-- 3. Ensure status column accepts required values (the column already exists;
--    SQL Server does not enforce ENUM, so no change needed unless a CHECK
--    constraint with an old list was added previously).
-- If you previously added a CHECK constraint, drop it first:
-- ALTER TABLE dbo.appointments DROP CONSTRAINT <constraint_name>;
-- Then add the updated one:
IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('dbo.appointments')
      AND name = 'CK_appointments_status'
)
BEGIN
    ALTER TABLE dbo.appointments
    ADD CONSTRAINT CK_appointments_status
    CHECK (status IN ('pending','approved','in_progress','completed','rejected','cancelled','rescheduled'));
END
GO

-- 4. Create appointment_notes table if it does not exist
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = 'dbo'
      AND TABLE_NAME   = 'appointment_notes'
)
BEGIN
    CREATE TABLE dbo.appointment_notes (
        note_id        INT IDENTITY(1,1) PRIMARY KEY,
        appointment_id INT           NOT NULL,
        note_text      NVARCHAR(MAX) NOT NULL,
        officer_id     INT           NULL,
        created_at     DATETIME      NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_appointment_notes_appointment
            FOREIGN KEY (appointment_id) REFERENCES dbo.appointments(appointment_id),
        CONSTRAINT FK_appointment_notes_officer
            FOREIGN KEY (officer_id) REFERENCES dbo.officers(officer_id)
    );
END
GO
