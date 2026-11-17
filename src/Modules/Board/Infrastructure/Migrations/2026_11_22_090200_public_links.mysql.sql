-- A secret, read-only link to one board. Only a SHA-256 hash of the secret is
-- kept; a new link replaces the old one, and turning it off deletes the row.
-- It works only while the workspace rules allow public links.
CREATE TABLE board_public_links (
    board_id   INT UNSIGNED NOT NULL PRIMARY KEY,
    token_hash CHAR(64)     NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL,
    UNIQUE KEY uq_board_public_links_token (token_hash),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
