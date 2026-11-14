CREATE TABLE attachments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id   TEXT    NOT NULL UNIQUE,
    card_id     INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    board_id    INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    uploader_id INTEGER NOT NULL REFERENCES users(id),
    kind        TEXT    NOT NULL CHECK (kind IN ('file', 'link')),
    name        TEXT    NOT NULL,
    url         TEXT,
    mime        TEXT,
    size        INTEGER NOT NULL DEFAULT 0,
    stored_name TEXT    UNIQUE,
    created_at  TEXT    NOT NULL
);

-- A card's attachments, newest first; counts per card for the board tiles.
CREATE INDEX idx_attachments_card ON attachments (card_id, id);
-- Bytes per board, for the quota.
CREATE INDEX idx_attachments_board ON attachments (board_id);
