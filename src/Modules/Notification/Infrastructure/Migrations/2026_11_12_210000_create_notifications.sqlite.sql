-- Who follows a card or a whole board. board_id is kept on card watches too,
-- so "everyone watching this card or its board" is two index lookups.
CREATE TABLE watches (
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    target     TEXT    NOT NULL CHECK (target IN ('board', 'card')),
    target_id  INTEGER NOT NULL,
    board_id   INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    created_at TEXT    NOT NULL,
    PRIMARY KEY (user_id, target, target_id)
);
CREATE INDEX idx_watches_target ON watches (target, target_id);

CREATE TABLE notifications (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type       TEXT    NOT NULL,
    actor_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
    board_id   INTEGER REFERENCES boards(id) ON DELETE CASCADE,
    card_id    INTEGER REFERENCES cards(id) ON DELETE CASCADE,
    subject    TEXT    NOT NULL,
    data       TEXT    NOT NULL DEFAULT '{}',
    link       TEXT    NOT NULL,
    -- One notification per user per event: a second insert with the same key is refused.
    dedupe_key TEXT    NOT NULL,
    created_at TEXT    NOT NULL,
    read_at    TEXT,
    UNIQUE (user_id, dedupe_key)
);
-- The bell: a user's newest first (keyset by id), and their unread count.
CREATE INDEX idx_notifications_user ON notifications (user_id, id);
CREATE INDEX idx_notifications_unread ON notifications (user_id, read_at);

CREATE TABLE notification_settings (
    user_id       INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    email_enabled INTEGER NOT NULL DEFAULT 1
);
