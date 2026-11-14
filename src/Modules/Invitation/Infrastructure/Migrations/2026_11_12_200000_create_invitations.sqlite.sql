-- Only a SHA-256 hash of the link's secret is kept: a leaked database can't
-- be used to join anyone's workspace.
CREATE TABLE invitations (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id    TEXT    NOT NULL UNIQUE,
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    email        TEXT    NOT NULL,
    role         TEXT    NOT NULL CHECK (role IN ('owner', 'admin', 'member', 'guest')),
    board_role   TEXT    NOT NULL CHECK (board_role IN ('manager', 'member', 'viewer')),
    token_hash   TEXT    NOT NULL UNIQUE,
    invited_by   INTEGER NOT NULL REFERENCES users(id),
    created_at   TEXT    NOT NULL,
    sent_at      TEXT    NOT NULL,
    expires_at   TEXT    NOT NULL,
    accepted_at  TEXT,
    accepted_by  INTEGER REFERENCES users(id),
    revoked_at   TEXT
);

-- Open invitations of a workspace, and "is this email already invited?".
CREATE INDEX idx_invitations_workspace ON invitations (workspace_id, email);
-- How many invitations someone sent lately (rate limit).
CREATE INDEX idx_invitations_sender ON invitations (invited_by, sent_at);

-- The boards an invitation adds the person to.
CREATE TABLE invitation_boards (
    invitation_id INTEGER NOT NULL REFERENCES invitations(id) ON DELETE CASCADE,
    board_id      INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    PRIMARY KEY (invitation_id, board_id)
);
