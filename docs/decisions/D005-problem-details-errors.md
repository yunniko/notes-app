# D005 · Errors are RFC 9457 problem+json with field errors
Date: 2026-11-02 · Goal: G-001 M2 · Status: active (superseded by: —)
Context: the UI needs to tell "fix this field" apart from "not found", "someone else saved" and "server down", and API clients need one predictable shape.
Decision: every error is `application/problem+json` with `type`, `title`, `status`, `detail`. A 422 adds an `errors` map of field to message, and a 409 adds `current`. 500s never expose internals.
Force: judgment — a standard shape beats a home-grown one, but nothing outside the project requires it.
Rejected: a custom `{error: ...}` envelope (a format reviewers would have to learn); HTTP 400 for validation (it conflates malformed requests with invalid content).
Consequence: new failure modes are thrown as domain exceptions and mapped in `backend/src/Http/ProblemDetailsErrorHandler.php`, never formatted inside an action.
Evidence: `backend/tests/Integration/NotesApiTest.php`; `frontend/src/api/notes.ts`
