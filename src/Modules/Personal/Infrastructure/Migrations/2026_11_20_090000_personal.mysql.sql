-- A personal inbox per person, outside every workspace and board.
CREATE TABLE inbox_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id   CHAR(26) NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(500) NOT NULL,
    description TEXT NULL,
    due_date    DATE NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    UNIQUE KEY uq_inbox_items_public (public_id),
    KEY idx_inbox_items_user (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Files kept with an inbox item until it becomes a card (stored like attachments, under a random name).
CREATE TABLE inbox_files (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id   CHAR(26) NOT NULL,
    item_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(150) NOT NULL,
    mime        VARCHAR(100) NOT NULL,
    size        INT UNSIGNED NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    created_at  DATETIME NOT NULL,
    UNIQUE KEY uq_inbox_files_public (public_id),
    KEY idx_inbox_files_item (item_id),
    FOREIGN KEY (item_id) REFERENCES inbox_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Planned work time on a card, for one person. Separate from the card's due date.
--   start_minute: minutes after midnight, or NULL for "any time that day".
CREATE TABLE planner_sessions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    card_id      INT UNSIGNED NOT NULL,
    day          DATE NOT NULL,
    start_minute SMALLINT UNSIGNED NULL,
    minutes      SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    created_at   DATETIME NOT NULL,
    KEY idx_planner_sessions_user (user_id, day),
    KEY idx_planner_sessions_card (card_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One private calendar (iCal) link per person; only the hash of its secret is kept.
CREATE TABLE calendar_feeds (
    user_id      INT UNSIGNED NOT NULL PRIMARY KEY,
    token_hash   CHAR(64) NOT NULL,
    created_at   DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    UNIQUE KEY uq_calendar_feeds_token (token_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
