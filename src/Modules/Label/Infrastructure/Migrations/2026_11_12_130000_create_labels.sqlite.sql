CREATE TABLE labels (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id   INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    name       TEXT    NOT NULL,
    color      TEXT    NOT NULL,
    created_at TEXT    NOT NULL
);

CREATE INDEX idx_labels_board ON labels (board_id, name);

-- A join table, not a comma-separated column: "labels of these cards"
-- and "cards with this label" are both index lookups.
CREATE TABLE card_labels (
    card_id  INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    label_id INTEGER NOT NULL REFERENCES labels(id) ON DELETE CASCADE,
    PRIMARY KEY (card_id, label_id)
);

CREATE INDEX idx_card_labels_label ON card_labels (label_id);
