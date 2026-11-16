-- Batch 2 (daily use): list colors, saved searches, per-person board display settings.

-- One of the board palette names, or NULL for the plain list. The name stays the list's text.
ALTER TABLE lists ADD COLUMN color VARCHAR(20) NULL;

-- A search someone named and kept. "query" is the search page's query string;
-- results are computed for whoever opens it, with their own permissions.
CREATE TABLE saved_searches (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id  CHAR(26) NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(80) NOT NULL,
    query      VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_saved_searches_public (public_id),
    KEY idx_saved_searches_user (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What one person wants to see on the cards of one board; other members are not affected.
CREATE TABLE board_preferences (
    user_id      INT UNSIGNED NOT NULL,
    board_id     INT UNSIGNED NOT NULL,
    card_display VARCHAR(500) NOT NULL DEFAULT '{}',
    updated_at   DATETIME NOT NULL,
    PRIMARY KEY (user_id, board_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
