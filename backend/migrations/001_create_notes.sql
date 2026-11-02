-- Notes. `version` supports optimistic locking (D004); timestamps are UTC with ms precision.
-- content is MEDIUMTEXT: 20,000 characters of utf8mb4 can exceed TEXT's 65,535-byte limit.
CREATE TABLE notes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(200) NOT NULL,
    content    MEDIUMTEXT   NOT NULL,
    priority   ENUM('low', 'medium', 'high') NOT NULL,
    version    INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX notes_updated_at (updated_at),
    INDEX notes_priority_updated_at (priority, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
