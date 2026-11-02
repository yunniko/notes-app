# D012 · Argon2id at OWASP minimums; ten failed logins lock a username for 15 minutes
Date: 2026-11-02 · Goal: G-002 M1 · Status: active (superseded by: —)
Context: the app stores passwords and accepts logins from the public internet.
Decision: `password_hash` with Argon2id, 19 MiB, two passes, one lane; older hashes are upgraded at the next login. An unknown username is verified against a dummy hash, so timing does not reveal accounts. Ten failures for one username within 15 minutes answer 429 with `Retry-After` until the window passes.
Force: judgment — OWASP Password Storage Cheat Sheet minimums; PHP's defaults (64 MiB, four passes) cost several times more per login on a shared server.
Rejected: bcrypt (truncates at 72 bytes); per-IP limits (the API sees the proxy, and shared IPs would lock out strangers); CAPTCHA (a third-party service).
Consequence: anyone can lock a known username for 15 minutes; acceptable for a demo, the first thing to revisit for real users.
Evidence: `backend/tests/Unit/AuthServiceTest.php`; `backend/tests/Unit/PasswordHasherTest.php`
