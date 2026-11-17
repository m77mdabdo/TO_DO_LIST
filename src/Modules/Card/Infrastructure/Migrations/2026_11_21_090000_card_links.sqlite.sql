-- A card linked to another card of the same workspace. One row links both ways:
-- each card shows the other. Details of the other card are only shown to people
-- who can open its board, checked every time the card is shown.
CREATE TABLE card_links (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    card_id        INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    linked_card_id INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    created_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at     TEXT    NOT NULL,
    UNIQUE (card_id, linked_card_id)
);
CREATE INDEX idx_card_links_linked ON card_links (linked_card_id);
