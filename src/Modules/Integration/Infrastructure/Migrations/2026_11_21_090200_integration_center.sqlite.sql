-- Ready-made integrations of a workspace (Slack first). The secret part of the
-- settings (e.g. the Slack webhook address) is encrypted with APP_KEY; only its
-- last characters are kept in clear to show which one is set.
--   status: active, paused, disconnected (secret wiped, history kept)
CREATE TABLE integrations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id       TEXT    NOT NULL UNIQUE,
    workspace_id    INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    type            TEXT    NOT NULL,
    name            TEXT    NOT NULL,
    secret          TEXT,
    secret_hint     TEXT    NOT NULL DEFAULT '',
    board_ids       TEXT    NOT NULL DEFAULT '[]',
    events          TEXT    NOT NULL DEFAULT '[]',
    status          TEXT    NOT NULL DEFAULT 'active',
    connected_by    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    last_success_at TEXT,
    last_error      TEXT    NOT NULL DEFAULT '',
    last_error_at   TEXT,
    created_at      TEXT    NOT NULL,
    updated_at      TEXT    NOT NULL
);
CREATE INDEX idx_integrations_workspace ON integrations (workspace_id, status);

-- Who changed what on an integration (connected, settings, rotated, paused, disconnected, tested).
CREATE TABLE integration_log (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    integration_id INTEGER NOT NULL REFERENCES integrations(id) ON DELETE CASCADE,
    actor_id       INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action         TEXT    NOT NULL,
    details        TEXT    NOT NULL DEFAULT '',
    created_at     TEXT    NOT NULL
);
CREATE INDEX idx_integration_log ON integration_log (integration_id, id);

-- Messages waiting to be sent by bin/webhooks.php, with retries.
CREATE TABLE integration_deliveries (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    integration_id  INTEGER NOT NULL REFERENCES integrations(id) ON DELETE CASCADE,
    board_id        INTEGER REFERENCES boards(id) ON DELETE CASCADE,
    event           TEXT    NOT NULL,
    payload         TEXT    NOT NULL,
    status          TEXT    NOT NULL DEFAULT 'pending',
    attempts        INTEGER NOT NULL DEFAULT 0,
    next_attempt_at TEXT    NOT NULL,
    response_code   INTEGER,
    error           TEXT    NOT NULL DEFAULT '',
    created_at      TEXT    NOT NULL,
    delivered_at    TEXT
);
CREATE INDEX idx_integration_deliveries_queue ON integration_deliveries (status, next_attempt_at);
CREATE INDEX idx_integration_deliveries_one ON integration_deliveries (integration_id, id);
