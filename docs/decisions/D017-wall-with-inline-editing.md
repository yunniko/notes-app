# D017 · Notes live on one wall and are edited on their own card
Date: 2026-11-02 · Goal: G-004 M3 · Status: active (superseded by: —)
Context: the supplied visual design puts every note on a masonry wall with a quick-note composer and edits a note in place, with no note pages.
Decision: `/` and `/archive` show the notes as sheets; Edit turns one sheet into the form, long notes open in place with Read more, and `/notes/...` addresses redirect to the wall.
Force: requirement — the product request: apply the supplied design in full, with editing on the card. The redirect for old addresses is judgment.
Rejected: keeping the read-only page beside the wall (the design has none); a modal editor (the design edits on the card).
Consequence: read-only stays the default (D015's requirement): a sheet is a form only after Edit. One sheet edits at a time; leaving with unsaved edits asks first. The conflict flow of D004 renders inside the card.
Evidence: `frontend/e2e/notes.spec.ts` ("editing on the card", "addresses"); `frontend/src/views/NotesWallView.vue`
