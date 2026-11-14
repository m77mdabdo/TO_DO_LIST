CREATE TABLE api_tokens (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id    TEXT    NOT NULL UNIQUE,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name         TEXT    NOT NULL,
    -- SHA-256 of the secret; the secret itself is shown once and never stored.
    token_hash   TEXT    NOT NULL UNIQUE,
    scope        TEXT    NOT NULL CHECK (scope IN ('read', 'write')),
    board_ids    TEXT    NOT NULL DEFAULT '[]',
    expires_at   TEXT,
    last_used_at TEXT,
    created_at   TEXT    NOT NULL,
    revoked_at   TEXT
);

CREATE INDEX idx_api_tokens_user ON api_tokens (user_id, id);

CREATE TABLE webhooks (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id  TEXT    NOT NULL UNIQUE,
    board_id   INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    url        TEXT    NOT NULL,
    secret     TEXT    NOT NULL,
    events     TEXT    NOT NULL DEFAULT '[]',
    enabled    INTEGER NOT NULL DEFAULT 1,
    created_by INTEGER NOT NULL REFERENCES users(id),
    created_at TEXT    NOT NULL
);

CREATE INDEX idx_webhooks_board ON webhooks (board_id, enabled);

CREATE TABLE webhook_deliveries (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    webhook_id      INTEGER NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
    event           TEXT    NOT NULL,
    payload         TEXT    NOT NULL,
    status          TEXT    NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
    attempts        INTEGER NOT NULL DEFAULT 0,
    next_attempt_at TEXT    NOT NULL,
    response_code   INTEGER,
    error           TEXT    NOT NULL DEFAULT '',
    created_at      TEXT    NOT NULL,
    delivered_at    TEXT
);

-- The sender's queue: what is due now.
CREATE INDEX idx_webhook_deliveries_queue ON webhook_deliveries (status, next_attempt_at);
CREATE INDEX idx_webhook_deliveries_hook ON webhook_deliveries (webhook_id, id);
