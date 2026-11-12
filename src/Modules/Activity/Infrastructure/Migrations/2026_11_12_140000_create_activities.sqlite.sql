CREATE TABLE activities (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id   INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    card_id    INTEGER REFERENCES cards(id) ON DELETE SET NULL,
    actor_id   INTEGER NOT NULL REFERENCES users(id),
    type       TEXT    NOT NULL,
    subject    TEXT    NOT NULL,
    data       TEXT    NOT NULL DEFAULT '{}',
    created_at TEXT    NOT NULL
);

-- Keyset pagination: "WHERE board_id = ? AND id < ? ORDER BY id DESC LIMIT n"
-- is an index seek however deep the page is (OFFSET would scan every skipped row).
CREATE INDEX idx_activities_board ON activities (board_id, id);
CREATE INDEX idx_activities_card ON activities (card_id, id);
