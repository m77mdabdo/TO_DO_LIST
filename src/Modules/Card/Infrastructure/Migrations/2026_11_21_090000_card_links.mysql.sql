-- A card linked to another card of the same workspace. One row links both ways:
-- each card shows the other. Details of the other card are only shown to people
-- who can open its board, checked every time the card is shown.
CREATE TABLE card_links (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    card_id        INT UNSIGNED NOT NULL,
    linked_card_id INT UNSIGNED NOT NULL,
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL,
    UNIQUE KEY uq_card_links (card_id, linked_card_id),
    KEY idx_card_links_linked (linked_card_id),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (linked_card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
