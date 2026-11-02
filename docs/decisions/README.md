# Decisions

One file per significant decision; append-only (a reversal is a new file).

- [D001](D001-backend-php-slim-mariadb.md) — Backend: PHP 8.5 + Slim 4 + PDO on MariaDB 12.3 — active
- [D002](D002-frontend-vue-typescript.md) — Frontend: Vue 3 + TypeScript + Vite, no component library — active
- [D003](D003-minimal-sql-migrator.md) — A 50-line SQL migrator instead of a migration library — active
- [D004](D004-optimistic-locking.md) — Updates carry the version they edited; a stale one gets 409 — active
- [D005](D005-problem-details-errors.md) — Errors are RFC 9457 problem+json with field errors — active
- [D006](D006-single-origin-nginx.md) — nginx serves the SPA and proxies /api to PHP-FPM on one origin — active
- [D007](D007-filter-in-url.md) — The priority filter is a multi-select stored in the URL — active
- [D008](D008-e2e-against-docker-stack.md) — End-to-end tests run against the real Docker stack — active
- [D009](D009-port-8088.md) — The app is published on 127.0.0.1:8088 — active
