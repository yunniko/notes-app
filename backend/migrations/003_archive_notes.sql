-- Archiving hides a note from the main list without deleting it (D013).
-- The list index gains archived_at, so both the active and the archived list are index range scans.
ALTER TABLE notes
    ADD COLUMN archived_at DATETIME(3) NULL DEFAULT NULL AFTER priority,
    DROP INDEX notes_owner_updated_at,
    ADD INDEX notes_owner_archived_at_updated_at (user_id, archived_at, updated_at);
