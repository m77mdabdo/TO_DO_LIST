-- A counter per board, raised on every change, so open board pages can ask "anything new?" cheaply.
CREATE TABLE board_versions (
    board_id   INTEGER PRIMARY KEY REFERENCES boards(id) ON DELETE CASCADE,
    version    INTEGER NOT NULL DEFAULT 0,
    actor_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
    changed_at TEXT    NOT NULL
);
