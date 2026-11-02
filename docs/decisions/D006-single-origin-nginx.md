# D006 · nginx serves the SPA and proxies /api to PHP-FPM on one origin
Date: 2026-11-02 · Goal: G-001 M3 · Status: active (superseded by: —)
Context: the browser needs the built SPA and the API, and the reviewer should be able to start everything with one command.
Decision: the `web` container builds the SPA and serves it with nginx, which forwards `/api/` over FastCGI to the `api` PHP-FPM container. Only nginx publishes a port, bound to 127.0.0.1.
Force: judgment — same origin removes CORS completely and mirrors a realistic production setup.
Rejected: `php -S` built-in server (development only, single-threaded); separate origins with CORS (more configuration and preflight requests with no benefit here).
Consequence: the API is reached only through `/api`. In development Vite proxies the same path (`frontend/vite.config.ts`), so the frontend code has a single base URL.
Evidence: `frontend/nginx/default.conf`; `docker-compose.yml`
