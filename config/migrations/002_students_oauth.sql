-- Migration: Add Microsoft OAuth fields to dbo.students
-- Run once against the student_violations database.

IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'dbo'
      AND TABLE_NAME = 'students'
      AND COLUMN_NAME = 'microsoft_id'
)
BEGIN
    ALTER TABLE dbo.students ADD microsoft_id NVARCHAR(255) NULL;
END
ELSE
BEGIN
    ALTER TABLE dbo.students ALTER COLUMN microsoft_id NVARCHAR(255) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'dbo'
      AND TABLE_NAME = 'students'
      AND COLUMN_NAME = 'oauth_token'
)
BEGIN
    ALTER TABLE dbo.students ADD oauth_token NVARCHAR(MAX) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'dbo'
      AND TABLE_NAME = 'students'
      AND COLUMN_NAME = 'last_login'
)
BEGIN
    ALTER TABLE dbo.students ADD last_login DATETIME NULL;
END
GO
