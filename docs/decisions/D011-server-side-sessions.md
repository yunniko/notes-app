# D011 · Server-side sessions in an HttpOnly cookie, with a same-origin guard
Date: 2026-11-02 · Goal: G-002 M1 · Status: active (superseded by: —)
Context: the SPA and the API share one origin (D006), and a logged-in user must stay logged in across reloads.
Decision: login sets `notes_session`, a random 256-bit token (HttpOnly, SameSite=Lax, Path=/api, 30 days, Secure over HTTPS). The `sessions` table stores only its SHA-256. Unsafe requests must come from the same origin, judged by `Sec-Fetch-Site`, or by `Origin` against `Host` in browsers without it.
Force: judgment — fits one origin and one database; logout and expiry take effect immediately.
Rejected: JWT in localStorage (readable by any injected script, cannot be revoked); PHP native sessions (file locking, a second store to run); a CSRF token (the same-origin check covers it without extra round trips).
Consequence: the SPA sends no auth header; a cross-origin client would need CORS and a new decision.
Evidence: `backend/tests/Integration/AuthApiTest.php`
