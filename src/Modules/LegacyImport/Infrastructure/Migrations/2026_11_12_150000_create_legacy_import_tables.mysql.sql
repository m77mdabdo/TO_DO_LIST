CREATE TABLE legacy_task_map (
    task_id     INT UNSIGNED PRIMARY KEY,
    card_id     INT UNSIGNED NULL,
    user_id     INT UNSIGNED NOT NULL,
    imported_at DATETIME NOT NULL,
    KEY idx_legacy_task_map_user (user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_boards (
    user_id       INT UNSIGNED PRIMARY KEY,
    board_id      INT UNSIGNED NOT NULL,
    todo_list_id  INT UNSIGNED NOT NULL,
    doing_list_id INT UNSIGNED NOT NULL,
    done_list_id  INT UNSIGNED NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
