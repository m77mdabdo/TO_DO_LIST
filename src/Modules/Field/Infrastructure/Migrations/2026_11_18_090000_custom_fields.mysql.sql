-- A board's custom fields. Options (for "select") are JSON: [{"id": "o1", "name": "High"}].
CREATE TABLE custom_fields (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    board_id   INT UNSIGNED NOT NULL,
    name       VARCHAR(60) NOT NULL,
    type       VARCHAR(20) NOT NULL,
    options    TEXT NOT NULL,
    position   INT NOT NULL DEFAULT 0,
    on_card    TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    KEY idx_custom_fields_board (board_id, position),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One value per card and field; a field with no value has no row.
CREATE TABLE card_field_values (
    card_id    INT UNSIGNED NOT NULL,
    field_id   INT UNSIGNED NOT NULL,
    value      TEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (card_id, field_id),
    KEY idx_card_field_values_field (field_id),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
