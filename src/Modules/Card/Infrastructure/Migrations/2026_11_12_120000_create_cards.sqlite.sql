CREATE TABLE cards (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id    TEXT    NOT NULL UNIQUE,
    board_id     INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    list_id      INTEGER NOT NULL REFERENCES lists(id) ON DELETE CASCADE,
    title        TEXT    NOT NULL,
    description  TEXT,
    position     TEXT    NOT NULL,
    start_at     TEXT,
    due_at       TEXT,
    completed_at TEXT,
    archived_at  TEXT,
    created_by   INTEGER NOT NULL REFERENCES users(id),
    created_at   TEXT    NOT NULL,
    updated_at   TEXT    NOT NULL
);

-- Cards of a list in order, and "the card after X" as one index seek.
CREATE INDEX idx_cards_list_position ON cards (list_id, position);
-- The whole board's open cards in one range scan (board page, no N+1).
CREATE INDEX idx_cards_board ON cards (board_id, archived_at);
-- Due-date reminders scan a date range, not every card.
CREATE INDEX idx_cards_due ON cards (due_at);
