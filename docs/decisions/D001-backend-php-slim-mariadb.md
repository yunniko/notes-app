# D001 · Backend: PHP 8.5 + Slim 4 + PDO on MariaDB 12.3
Date: 2026-11-02 · Goal: G-001 M1 · Status: active (superseded by: —)
Context: the brief asks for a PHP backend and a MariaDB or MongoDB database, both on current LTS/stable versions.
Decision: PHP 8.5 with Slim 4, PHP-DI autowiring and plain PDO with real prepared statements, storing data in MariaDB 12.3 LTS.
Force: requirement for PHP and MariaDB (the brief); judgment for Slim and for having no ORM.
Rejected: Laravel/Symfony (most of the framework would go unused on four endpoints, and it would hide the HTTP and SQL handling the reviewers want to read); MongoDB (notes are flat rows with a filtered, ordered list, so a relational index fits exactly); Doctrine ORM (it adds mapping for one table).
Consequence: SQL lives only in `backend/src/Infrastructure/PdoNoteRepository.php`, behind the `NoteRepository` interface, so a different store is swapped in there and nowhere else.
Evidence: `backend/composer.json`; `backend/tests/Integration/NotesApiTest.php`
