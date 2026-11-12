CREATE TABLE activities (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    board_id   INT UNSIGNED NOT NULL,
    card_id    INT UNSIGNED NULL,
    actor_id   INT UNSIGNED NOT NULL,
    type       VARCHAR(40)  NOT NULL,
    subject    VARCHAR(200) NOT NULL,
    data       JSON         NOT NULL,
    created_at DATETIME     NOT NULL,
    KEY idx_activities_board (board_id, id),
    KEY idx_activities_card (card_id, id),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL,
    FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
