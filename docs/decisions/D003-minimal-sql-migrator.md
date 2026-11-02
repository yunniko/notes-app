# D003 · A 65-line SQL migrator instead of a migration library
Date: 2026-11-02 · Goal: G-001 M1 · Status: active (superseded by: —)
Context: the schema must be created reproducibly on a fresh `docker compose up`, and must be able to evolve later.
Decision: ordered `.sql` files in `backend/migrations/`, each applied once and recorded in a `schema_migrations` table. The API container's entrypoint runs them before PHP-FPM starts.
Force: judgment — one table does not justify a migration framework, but a raw init script could never change once a database exists.
Rejected: Doctrine Migrations/Phinx (heavy dependency and configuration for one file); MariaDB `docker-entrypoint-initdb.d` (it runs only on an empty volume, so later changes would never be applied).
Consequence: migrations are append-only, so an applied file is never edited; a change ships as a new numbered file.
Evidence: `backend/src/Infrastructure/Migrator.php`; `backend/docker-entrypoint.sh`
