# D013 · Archive is a timestamp on the note; delete is permanent behind a confirmation
Date: 2026-11-02 · Goal: G-002 M2 · Status: active (superseded by: —)
Context: users asked to archive notes (out of the main list, restorable) and to delete them.
Decision: `archived_at` is NULL for active notes. `POST /notes/{id}/archive` and `/restore` are idempotent; they bump `version` only when the state changes and leave `updatedAt` alone. `DELETE /notes/{id}` removes the row; the UI asks for confirmation first. Archived notes stay editable.
Force: requirement — the feature request: a restorable archive, and a delete that is permanent after a confirmation. The endpoint shapes are judgment.
Rejected: soft delete with undo (a purge job for a small app); `PATCH {"archived": true}` (one verb per action reads more clearly in the API and the UI); archive as a priority-like status (it is orthogonal to priority, and both filters combine).
Consequence: a stale editor conflicts (409) after the note was archived elsewhere, because the version moved (D004).
Evidence: `backend/tests/Integration/NotesApiTest.php` ("testArchiveAndRestore", "testDeleteIsPermanent")
