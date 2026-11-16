-- An item turned into a card keeps a link to that card.
ALTER TABLE checklist_items
    ADD COLUMN card_link_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_checklist_items_card_link FOREIGN KEY (card_link_id) REFERENCES cards(id) ON DELETE SET NULL;

-- Checklists whose ticked items one person chose to hide. Only that person is affected.
CREATE TABLE checklist_hidden (
    user_id      INT UNSIGNED NOT NULL,
    checklist_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, checklist_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
