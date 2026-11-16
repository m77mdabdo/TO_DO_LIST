-- How one person set up a view of a board (the table's columns, sort and grouping;
-- the dashboard's widgets). Nobody else's view changes.
CREATE TABLE view_settings (
    user_id    INT UNSIGNED NOT NULL,
    board_id   INT UNSIGNED NOT NULL,
    view       VARCHAR(20) NOT NULL,
    settings   VARCHAR(4000) NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, board_id, view),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
