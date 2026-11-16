-- A personal inbox per person, outside every workspace and board.
CREATE TABLE inbox_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id   TEXT    NOT NULL UNIQUE,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title       TEXT    NOT NULL,
    description TEXT,
    due_date    TEXT,
    created_at  TEXT    NOT NULL,
    updated_at  TEXT    NOT NULL
);
CREATE INDEX idx_inbox_items_user ON inbox_items (user_id, created_at);

-- Files kept with an inbox item until it becomes a card (stored like attachments, under a random name).
CREATE TABLE inbox_files (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id   TEXT    NOT NULL UNIQUE,
    item_id     INTEGER NOT NULL REFERENCES inbox_items(id) ON DELETE CASCADE,
    name        TEXT    NOT NULL,
    mime        TEXT    NOT NULL,
    size        INTEGER NOT NULL,
    stored_name TEXT    NOT NULL,
    created_at  TEXT    NOT NULL
);
CREATE INDEX idx_inbox_files_item ON inbox_files (item_id);

-- Planned work time on a card, for one person. Separate from the card's due date.
--   start_minute: minutes after midnight, or NULL for "any time that day".
CREATE TABLE planner_sessions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    card_id      INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    day          TEXT    NOT NULL,
    start_minute INTEGER,
    minutes      INTEGER NOT NULL DEFAULT 60,
    created_at   TEXT    NOT NULL
);
CREATE INDEX idx_planner_sessions_user ON planner_sessions (user_id, day);
CREATE INDEX idx_planner_sessions_card ON planner_sessions (card_id);

-- One private calendar (iCal) link per person; only the hash of its secret is kept.
CREATE TABLE calendar_feeds (
    user_id      INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    token_hash   TEXT    NOT NULL UNIQUE,
    created_at   TEXT    NOT NULL,
    last_used_at TEXT
);
