# D004 · Updates carry the version they edited; a stale one gets 409
Date: 2026-11-02 · Goal: G-001 M2 · Status: active (superseded by: —)
Context: two tabs or devices can edit the same note. With a plain last-write-wins PUT, one person's edit silently disappears.
Decision: a `version` column; PUT must send the version it read, and the UPDATE is a compare-and-set (`WHERE id = ? AND version = ?`). On a mismatch the API answers 409 with the current note, and the UI lets the user load the other version or overwrite it knowingly.
Force: judgment — the brief only asks for update, but lost edits are the main edge case in a notes app.
Rejected: last-write-wins (silent data loss); `If-Match`/ETag headers (equivalent but less visible in a JSON body for reviewers and the SPA); row locks (they cannot span a user's editing time).
Consequence: every write path increments `version`; a new update endpoint must take an expected version too.
Evidence: `backend/tests/Integration/NotesApiTest.php`; `frontend/e2e/notes.spec.ts` ("a concurrent edit is detected")
