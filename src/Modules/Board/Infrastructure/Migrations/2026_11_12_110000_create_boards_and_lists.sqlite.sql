CREATE TABLE boards (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id    TEXT    NOT NULL UNIQUE,
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name         TEXT    NOT NULL,
    description  TEXT,
    color        TEXT    NOT NULL DEFAULT 'violet',
    visibility   TEXT    NOT NULL DEFAULT 'private' CHECK (visibility IN ('private', 'workspace')),
    created_by   INTEGER NOT NULL REFERENCES users(id),
    archived_at  TEXT,
    created_at   TEXT    NOT NULL,
    updated_at   TEXT    NOT NULL
);

-- The workspace home lists a workspace's open boards: one index range scan.
CREATE INDEX idx_boards_workspace ON boards (workspace_id, archived_at, name);

CREATE TABLE lists (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id    INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    name        TEXT    NOT NULL,
    position    TEXT    NOT NULL,
    archived_at TEXT,
    created_at  TEXT    NOT NULL,
    updated_at  TEXT    NOT NULL
);

-- Lists of a board come back already in order, and "the list after X" is an index seek.
CREATE INDEX idx_lists_board_position ON lists (board_id, position);
