CREATE TABLE board_members (
    board_id INT UNSIGNED NOT NULL,
    user_id  INT UNSIGNED NOT NULL,
    role     ENUM('manager', 'member', 'viewer') NOT NULL,
    added_at DATETIME NOT NULL,
    PRIMARY KEY (board_id, user_id),
    KEY idx_board_members_user (user_id),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
