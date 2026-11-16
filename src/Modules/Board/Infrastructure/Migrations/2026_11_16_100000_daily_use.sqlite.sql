-- Batch 2 (daily use): list colors, saved searches, per-person board display settings.

-- One of the board palette names, or NULL for the plain list. The name stays the list's text.
ALTER TABLE lists ADD COLUMN color TEXT;

-- A search someone named and kept. "query" is the search page's query string;
-- results are computed for whoever opens it, with their own permissions.
CREATE TABLE saved_searches (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id  TEXT    NOT NULL UNIQUE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name       TEXT    NOT NULL,
    query      TEXT    NOT NULL,
    created_at TEXT    NOT NULL,
    updated_at TEXT    NOT NULL
);
CREATE INDEX idx_saved_searches_user ON saved_searches (user_id, name);

-- What one person wants to see on the cards of one board; other members are not affected.
CREATE TABLE board_preferences (
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    board_id     INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    card_display TEXT    NOT NULL DEFAULT '{}',
    updated_at   TEXT    NOT NULL,
    PRIMARY KEY (user_id, board_id)
);
