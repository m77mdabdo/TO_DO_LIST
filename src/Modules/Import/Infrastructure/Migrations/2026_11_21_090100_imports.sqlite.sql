-- Imports (CSV into a board, a Trello export into a new board). An upload is
-- read once into a file under storage/imports and waits here as "pending"
-- until the person checks the preview and runs it, or cancels it.
--   kind: csv, trello    status: pending, done, cancelled
--   source_key: the Trello board id or the file's SHA-256, to spot a second import of the same thing
CREATE TABLE imports (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id    TEXT    NOT NULL UNIQUE,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    board_id     INTEGER REFERENCES boards(id) ON DELETE SET NULL,
    kind         TEXT    NOT NULL,
    status       TEXT    NOT NULL DEFAULT 'pending',
    file_name    TEXT    NOT NULL,
    source_key   TEXT    NOT NULL,
    summary      TEXT,
    created_at   TEXT    NOT NULL,
    finished_at  TEXT
);
CREATE INDEX idx_imports_source ON imports (workspace_id, kind, source_key);
CREATE INDEX idx_imports_user ON imports (user_id, status);
