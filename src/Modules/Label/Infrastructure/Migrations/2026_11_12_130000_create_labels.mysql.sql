CREATE TABLE labels (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    board_id   INT UNSIGNED NOT NULL,
    name       VARCHAR(60) NOT NULL,
    color      VARCHAR(20) NOT NULL,
    created_at DATETIME    NOT NULL,
    KEY idx_labels_board (board_id, name),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE card_labels (
    card_id  INT UNSIGNED NOT NULL,
    label_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (card_id, label_id),
    KEY idx_card_labels_label (label_id),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
