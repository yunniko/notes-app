-- Accounts and server-side sessions; every note gets an owner (see D010, D011).

-- Usernames are ASCII (validated), so the case-insensitive collation makes "Alice" and "alice" one account.
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(32)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    UNIQUE KEY users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Only a SHA-256 of the session token is stored, so a copy of this table yields no usable cookies.
CREATE TABLE sessions (
    token_hash BINARY(32)   NOT NULL PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    created_at DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    expires_at DATETIME(3)  NOT NULL,
    INDEX sessions_expires_at (expires_at),
    CONSTRAINT sessions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Failed logins per username, for throttling password guessing.
CREATE TABLE login_failures (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username  VARCHAR(32)  NOT NULL,
    failed_at DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX login_failures_username (username, failed_at),
    INDEX login_failures_failed_at (failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Notes written before accounts existed have no owner to give them to, so they are removed (D010).
DELETE FROM notes;

ALTER TABLE notes
    ADD COLUMN user_id INT UNSIGNED NOT NULL AFTER id,
    DROP INDEX notes_updated_at,
    DROP INDEX notes_priority_updated_at,
    ADD INDEX notes_owner_updated_at (user_id, updated_at),
    ADD INDEX notes_owner_priority_updated_at (user_id, priority, updated_at),
    ADD CONSTRAINT notes_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;
