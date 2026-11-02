# D008 · End-to-end tests run against the real Docker stack
Date: 2026-11-02 · Goal: G-001 M3 · Status: active (superseded by: —)
Context: the code users touch runs as nginx, then PHP-FPM, then MariaDB in containers. A mocked API would prove nothing about that path.
Decision: Playwright runs against the composed stack at `E2E_BASE_URL` (default http://localhost:8088), in desktop Chromium and a mobile viewport. Backend integration tests use a separate `notes_test` database.
Force: judgment — tests should exercise the path users actually run, including nginx, FastCGI and SQL.
Rejected: Playwright `webServer` with `vite dev` and a mocked API (it skips nginx, FastCGI and SQL); one shared database for tests and the app (tests would truncate real notes).
Consequence: e2e tests create uniquely titled notes and never assume an empty list. The integration base class refuses to run against the `notes` database.
Evidence: `frontend/playwright.config.ts`; `backend/tests/Integration/DatabaseTestCase.php`
