# D010 · Every note has an owner; another user's note is a 404
Date: 2026-11-02 · Goal: G-002 M1 · Status: active (superseded by: —)
Context: accounts were added, and notes must belong to the person who wrote them.
Decision: `notes.user_id` is NOT NULL with a cascading foreign key. Every repository statement carries `user_id = ?`, and another user's note answers 404 exactly like a missing one. Notes created before accounts existed are deleted by the migration.
Force: requirement — the brief: "notes should be attached to users"; deleting ownerless rows follows from NOT NULL, and the only such rows were test data.
Rejected: 403 for someone else's note (confirms the id exists); checking ownership in the service after loading (one forgotten check leaks a note); keeping old notes under a placeholder account (nobody could log in to see them).
Consequence: a new note query must take the owner id; the in-memory test repository enforces the same scoping.
Evidence: `backend/migrations/002_users_and_sessions.sql`; `backend/tests/Integration/NotesApiTest.php` ("testNotesBelongToTheirAuthor")
