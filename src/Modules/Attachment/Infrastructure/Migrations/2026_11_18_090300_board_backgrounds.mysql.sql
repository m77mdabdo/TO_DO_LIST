-- A picture behind a board. Stored like attachments: outside the web root, under a random name.
CREATE TABLE board_backgrounds (
    board_id    INT UNSIGNED NOT NULL PRIMARY KEY,
    stored_name VARCHAR(100) NOT NULL,
    mime        VARCHAR(100) NOT NULL,
    size        INT UNSIGNED NOT NULL,
    uploader_id INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
