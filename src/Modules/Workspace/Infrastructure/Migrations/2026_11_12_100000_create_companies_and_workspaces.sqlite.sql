CREATE TABLE companies (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id  TEXT    NOT NULL UNIQUE,
    name       TEXT    NOT NULL,
    created_by INTEGER NOT NULL REFERENCES users(id),
    created_at TEXT    NOT NULL
);

CREATE TABLE workspaces (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id   TEXT    NOT NULL UNIQUE,
    company_id  INTEGER NOT NULL UNIQUE REFERENCES companies(id) ON DELETE CASCADE,
    name        TEXT    NOT NULL,
    description TEXT,
    created_at  TEXT    NOT NULL
);

CREATE TABLE workspace_members (
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role         TEXT    NOT NULL CHECK (role IN ('owner', 'admin', 'member', 'guest')),
    joined_at    TEXT    NOT NULL,
    PRIMARY KEY (workspace_id, user_id)
);

-- "Which workspaces is this user in?" walks this index instead of the whole table.
CREATE INDEX idx_workspace_members_user ON workspace_members (user_id, joined_at);
