# D015 · A note opens read-only at /notes/{id}; the form is a separate route
Date: 2026-11-02 · Goal: G-003 M2 · Status: superseded (superseded by: D017)
Context: opening a note showed the edit form directly, so reading a note risked changing it.
Decision: `/notes/{id}` shows the note read-only with Edit, Archive/Restore and Delete; Edit opens `/notes/{id}/edit`. Save and Cancel replace the history entry with the note page, and Back from the note returns to the list URL it was opened from.
Force: requirement — the feature request asks for read-only notes that become editable through an Edit button. The separate route is judgment.
Rejected: a read/edit toggle on one route (a reload or a shared link would lose the mode, and Back could not leave edit mode); inline editing on the card (no room for long content or the conflict flow of D004).
Consequence: links to a note open the read-only page; only the Edit button leads to the form. Archive, restore and delete behave the same on the list and the page through `frontend/src/composables/useNoteActions.ts`.
Evidence: `frontend/e2e/notes.spec.ts` ("reading a note")
