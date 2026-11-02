# Notes

A small notes app: create a note with a title, content and priority, edit it, and list notes filtered by priority. Each user has a
private account; notes can be archived and restored, or deleted permanently.

- **Backend:** PHP 8.5, Slim 4, PDO, MariaDB 12.3 LTS
- **Frontend:** Vue 3 with TypeScript (Vite)
- **Runs with:** Docker Compose. One command starts the whole stack.
- **Live demo:** https://notes.app.julienika.cz

## Run it

You need Docker with Compose v2.

```sh
docker compose up --build
```

Then open **http://localhost:8088**. The first start takes a minute: it builds the images, and the API waits for MariaDB, then applies the
database migrations itself.

To use a different port or different credentials, copy `.env.example` to `.env` and edit it. Every port binds to `127.0.0.1` only.

Stop the stack with `docker compose down`. Add `-v` to also delete the database volume.

## Using it

- **Account:** register with a username (3–32 letters, digits, `.`, `-`, `_`) and a password of at least 8 characters, and you are logged
  in. Your notes are visible to you only. A logged-out visitor sees only the login and register pages; opening a note's link while logged
  out returns to that note after login. **Log out** is in the header.
- **List:** the newest change comes first. The priority chips filter the list, and several can be on at once. The filter is part of the URL
  (`/?priority=high,medium`), so a filtered view survives a reload and can be bookmarked.
- **Archive:** **Archive** on a card moves the note out of the main list into the **Archived** tab (`/archive`), newest archived first.
  **Restore** brings it back. The priority filter works in both tabs, and an archived note can still be opened and edited.
- **Delete:** **Delete** asks for confirmation, then removes the note permanently. There is no undo; archive is the reversible option.
- **Create / edit:** the title is required (at most 200 characters, surrounding whitespace is removed). Content is optional (at most 20,000
  characters, stored exactly as typed). Priority is low, medium or high.
- **Edit conflicts:** if the note was saved somewhere else (another tab or device) after you opened it, saving does not overwrite it.
  You see the other version and choose between **Discard mine, load theirs** and **Save mine anyway**.
- **Unsaved changes:** leaving the editor with unsaved changes asks first, and closing the tab triggers the browser's warning.

## Development and tests

| What                                                                 | Command                                                                                  |
| -------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Backend: format check, PHPStan (level max), unit + integration tests | `docker compose --profile dev run --rm api-dev`                                          |
| Frontend: format check, ESLint, type check, unit tests               | `cd frontend && npm ci && npm run check`                                                 |
| Frontend dev server with hot reload (proxies `/api` to :8088)        | `cd frontend && npm run dev`                                                             |
| End-to-end tests (Playwright, desktop + mobile)                      | stack running, then `cd frontend && npx playwright install chromium && npm run test:e2e` |

The backend checks run inside a PHP container, so there is no local PHP to install. Integration tests use a separate `notes_test`
database, and the test base class refuses to run against the real `notes` database. End-to-end tests drive the real stack: nginx, then
PHP-FPM, then MariaDB. Each test registers its own uniquely named account, so tests never see each other's notes. The local database
therefore collects test accounts; `docker compose down -v` resets it.

CI (`.github/workflows/ci.yml`) runs the same three groups on every push and pull request.

## API

All endpoints are under `/api` and exchange JSON. The session is an HttpOnly cookie (`notes_session`) set by register and login; every
notes endpoint needs it and acts on the logged-in user's notes only. Notes look like this:

```json
{
  "id": 72,
  "title": "Readme sample",
  "content": "Hello",
  "priority": "high",
  "version": 1,
  "createdAt": "2026-11-02T10:08:54+00:00",
  "updatedAt": "2026-11-02T10:08:54+00:00",
  "archivedAt": null
}
```

| Method and path                | Body                                                     | Success                                                                                          |
| ------------------------------ | -------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `POST /api/auth/register`      | `{"username", "password"}`                               | `201 {"data": user}` + session cookie                                                            |
| `POST /api/auth/login`         | `{"username", "password"}`                               | `200 {"data": user}` + new session cookie                                                        |
| `POST /api/auth/logout`        | —                                                        | `204`, session ended and cookie cleared                                                          |
| `GET /api/auth/me`             | —                                                        | `200 {"data": user}`, or `401`                                                                   |
| `GET /api/notes`               | —, optional `?priority=high,medium` and `?archived=true` | `200 {"data": [note, …]}`: active notes newest change first, archived ones newest archived first |
| `POST /api/notes`              | `{"title", "content"?, "priority"}`                      | `201 {"data": note}` + `Location` header                                                         |
| `GET /api/notes/{id}`          | —                                                        | `200 {"data": note}`                                                                             |
| `PUT /api/notes/{id}`          | `{"title", "content"?, "priority", "version"}`           | `200 {"data": note}` with `version` + 1                                                          |
| `POST /api/notes/{id}/archive` | —                                                        | `200 {"data": note}` with `archivedAt` set                                                       |
| `POST /api/notes/{id}/restore` | —                                                        | `200 {"data": note}` with `archivedAt: null`                                                     |
| `DELETE /api/notes/{id}`       | —                                                        | `204`, the note is gone                                                                          |
| `GET /api/health`              | —                                                        | `200 {"status": "ok"}`                                                                           |

`PUT` replaces the note's fields. `version` is the version the client last read: if someone saved in between, the answer is `409` and
nothing changes. Archive and restore are idempotent; a change of state raises `version` (so an editor open elsewhere sees a conflict) but
not `updatedAt`. A user is `{"id", "username", "createdAt"}`.

Errors use [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) `application/problem+json`:

```json
{
  "type": "about:blank",
  "status": 422,
  "title": "Validation failed",
  "detail": "The request contains invalid fields.",
  "errors": {
    "title": "Title is required.",
    "priority": "Priority must be one of: low, medium, high."
  }
}
```

| Status | When                                                                                                   |
| ------ | ------------------------------------------------------------------------------------------------------ |
| 400    | The body is not valid JSON                                                                             |
| 401    | Not logged in or session expired; on login, a wrong username or password                               |
| 403    | A state-changing request from another site (cross-site `Origin` or `Sec-Fetch-Site`)                   |
| 404    | No such note, someone else's note, or an id that is not a positive integer                             |
| 405    | Wrong method for the path                                                                              |
| 409    | Stale `version`. The body's `current` holds the note as it is now                                      |
| 415    | The body is not `application/json`                                                                     |
| 422    | Invalid fields (`errors` maps field to message), including an unknown filter value or a taken username |
| 429    | Too many failed logins for this username; `Retry-After` says when to try again                         |
| 500    | Unexpected failure. The details are logged and never sent to the client                                |

## Design choices

The reasoning for each choice, and the alternatives rejected, is in [`docs/decisions/`](docs/decisions/README.md), one short file per
decision. In brief:

- **Slim and plain PDO, no full framework or ORM** ([D001](docs/decisions/D001-backend-php-slim-mariadb.md)). A dozen endpoints over a
  few tables do not need Laravel or Doctrine, and without them the HTTP handling and the SQL can be read directly. The code is layered:
  - `Http` (actions, middleware, JSON in and out, the session cookie, error mapping)
  - `Application` (`NoteService`, `AuthService`, the use cases)
  - `Domain` (`Note`, `User`, `Priority`, validation, password hashing, the repository interfaces)
  - `Infrastructure` (the PDO repositories, migrations)

  The use cases are unit-tested against in-memory repositories. The full HTTP stack is integration-tested against MariaDB.

- **Optimistic locking** ([D004](docs/decisions/D004-optimistic-locking.md)). A note app's real failure mode is a silently lost edit. The
  update is one compare-and-set statement (`WHERE id = ? AND version = ?`), so no lock is held while someone types. The 409 response carries
  the current note, so the UI can show the user what changed.
- **Validation on both sides, server authoritative.** The browser checks the same limits for instant feedback. The server validates
  again and returns every field error at once, and the UI maps them onto the fields.
- **Standard error shape** ([D005](docs/decisions/D005-problem-details-errors.md)). Problem details with an `errors` map let the UI tell
  "fix this field", "not found", "conflict" and "server down" apart without parsing strings.
- **One origin** ([D006](docs/decisions/D006-single-origin-nginx.md)). nginx serves the built SPA and passes `/api/` to PHP-FPM, so there is
  no CORS to configure. Only nginx is published.
- **Filter in the URL** ([D007](docs/decisions/D007-filter-in-url.md)) as a multi-select. An unknown priority is rejected with 422, not
  silently ignored.
- **Migrations** ([D003](docs/decisions/D003-minimal-sql-migrator.md)). Numbered SQL files, applied once each by a 65-line migrator at
  container start, so the schema can evolve without wiping the volume.
- **Accounts and sessions** ([D010](docs/decisions/D010-notes-owned-by-users.md),
  [D011](docs/decisions/D011-server-side-sessions.md), [D012](docs/decisions/D012-password-hashing-and-throttle.md)). Every note has an
  owner and every notes query includes `user_id = ?`, so another user's note answers 404, the same as a missing one. Sessions are
  server-side: the cookie holds a random token and the database only its SHA-256, so logout and expiry take effect at once. Passwords are
  hashed with Argon2id; ten failed logins for a username within 15 minutes are answered with 429.
- **Archive and delete** ([D013](docs/decisions/D013-archive-and-permanent-delete.md)). Archiving sets an `archived_at` timestamp, so it
  is reversible and orders the archive. Delete removes the row, behind a confirmation dialog.
- **Database details.** `utf8mb4` with a Unicode 14 collation, so emoji and any script are stored correctly. Lengths are counted in
  characters (`mb_strlen` on the server, code points in the browser), not bytes. Timestamps are stored in UTC with millisecond precision, and
  the browser shows them in local time. Indexes lead with `user_id` and serve both lists, unfiltered and filtered by priority.

### Edge cases handled

- whitespace-only titles
- titles and content at their limits, and one past them
- emoji and non-Latin text
- duplicate or unknown priorities in the filter
- ids that are not numbers or overflow
- a missing JSON body, a non-object JSON body, a wrong content type
- concurrent edits, including a note archived or deleted in another tab
- a session that expires while the page is open (back to login, then back to where you were)
- a login redirect that points to another site (ignored)
- a username that differs only in letter case (treated as taken)
- leaving with unsaved changes
- a double-submitted form (the button is disabled while saving)
- an aborted list request when the filter changes quickly
- a server that cannot be reached

Each has an automated test in `backend/tests/`, `frontend/src/**/__tests__/` or `frontend/e2e/`, except the aborted list request, which
is handled in `NotesListView.vue` but not tested on its own.

### Accessibility and security

**Accessibility.** The app has semantic landmarks and headings, a skip link, labelled fields, and errors linked with `aria-describedby`.
Focus moves to the first invalid field. Filter chips use `aria-pressed`, and save confirmations are announced through a live region. The delete
confirmation is a native modal `<dialog>`: focus stays inside it and Escape cancels. It
supports keyboard-only use and follows the system light/dark theme.

**Security.**

- All SQL uses bound parameters with native prepares, and every notes query is scoped to the logged-in user.
- The session cookie is `HttpOnly`, `SameSite=Lax`, limited to `/api`, and `Secure` over HTTPS. State-changing requests from other sites
  are refused (403), on top of `SameSite`.
- Passwords are hashed with Argon2id. A login for an unknown username costs the same as for a known one, so timing does not reveal
  which usernames exist; failed logins are throttled per username.
- Note text is rendered as text, never as HTML.
- nginx sends a Content Security Policy and the usual hardening headers.
- `X-Powered-By` is removed, and PHP runs as `www-data`.

## Not in scope yet (roadmap)

What I would add next, roughly in order:

1. **Pagination**: a keyset cursor on `(updated_at, id)`, since the list is unbounded today.
2. **Search** across title and content (MariaDB full-text index).
3. **Account management**: change password, delete account, password reset (needs an email address and a mail service).
4. Archive and delete from the editor as well as the list; an undo for delete.
5. Markdown rendering of content (sanitised), tags, sorting options.

## Project layout

```
backend/            PHP API (src/, tests/, migrations/, bin/migrate.php)
frontend/           Vue SPA (src/, e2e/), nginx config, Dockerfile
docker/             MariaDB init script (creates the test database)
docs/decisions/     One file per design decision
docker-compose.yml  db, api, web (+ api-dev for checks)
```

## AI assistance

This project was built with the help of an AI coding assistant, [Claude Code](https://claude.com/claude-code). Commits it contributed to
carry a `Co-Authored-By: Claude` trailer.
