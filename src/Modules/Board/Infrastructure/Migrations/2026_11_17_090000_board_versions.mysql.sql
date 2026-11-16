-- A counter per board, raised on every change, so open board pages can ask "anything new?" cheaply.
CREATE TABLE board_versions (
    board_id   INT UNSIGNED NOT NULL PRIMARY KEY,
    version    INT UNSIGNED NOT NULL DEFAULT 0,
    actor_id   INT UNSIGNED NULL,
    changed_at DATETIME NOT NULL,
    CONSTRAINT fk_board_versions_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    CONSTRAINT fk_board_versions_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
