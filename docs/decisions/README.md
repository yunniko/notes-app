# Decisions

One file per significant decision; append-only (a reversal is a new file).

- [D001](D001-backend-php-slim-mariadb.md) — Backend: PHP 8.5 + Slim 4 + PDO on MariaDB 12.3 — active
- [D002](D002-frontend-vue-typescript.md) — Frontend: Vue 3 + TypeScript + Vite, no component library — active
- [D003](D003-minimal-sql-migrator.md) — A 65-line SQL migrator instead of a migration library — active
- [D004](D004-optimistic-locking.md) — Updates carry the version they edited; a stale one gets 409 — active
- [D005](D005-problem-details-errors.md) — Errors are RFC 9457 problem+json with field errors — active
- [D006](D006-single-origin-nginx.md) — nginx serves the SPA and proxies /api to PHP-FPM on one origin — active
- [D007](D007-filter-in-url.md) — The priority filter is a multi-select stored in the URL — active
- [D008](D008-e2e-against-docker-stack.md) — End-to-end tests run against the real Docker stack — active
- [D009](D009-port-8088.md) — The app is published on 127.0.0.1:8088 — active
- [D010](D010-notes-owned-by-users.md) — Every note has an owner; another user's note is a 404 — active
- [D011](D011-server-side-sessions.md) — Server-side sessions in an HttpOnly cookie, with a same-origin guard — active
- [D012](D012-password-hashing-and-throttle.md) — Argon2id at OWASP minimums; ten failed logins lock a username for 15 minutes — active
- [D013](D013-archive-and-permanent-delete.md) — Archive is a timestamp on the note; delete is permanent behind a confirmation — active
- [D014](D014-search-with-like.md) — Search is a substring LIKE per word, not a FULLTEXT index — active
- [D015](D015-read-only-note-page.md) — A note opens read-only at /notes/{id}; the form is a separate route — superseded by D017
- [D016](D016-sort-and-list-counts.md) — The list sorts by newest, oldest or priority, and carries both lists' counts — active
- [D017](D017-wall-with-inline-editing.md) — Notes live on one wall and are edited on their own card — active
- [D018](D018-composer-draft-per-account.md) — The quick-note draft belongs to one account — active
