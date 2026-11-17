-- Personal settings, two-step sign-in, and the list of signed-in devices.

-- The language the person chose; applied every time they sign in. NULL = the app default.
ALTER TABLE users ADD COLUMN locale TEXT;
-- Two-step sign-in (TOTP). The secret is encrypted with APP_KEY; recovery
-- codes are kept only as SHA-256 hashes (a JSON list), each usable once.
ALTER TABLE users ADD COLUMN two_factor_secret TEXT;
ALTER TABLE users ADD COLUMN two_factor_recovery TEXT;
ALTER TABLE users ADD COLUMN two_factor_enabled_at TEXT;
-- Set when the person deletes their account: the row stays (their old work
-- keeps an author) but holds no personal data and can't sign in.
ALTER TABLE users ADD COLUMN deleted_at TEXT;

-- One row per signed-in browser. The session holds a secret; only its hash is
-- stored. Deleting a row signs that device out on its next request.
CREATE TABLE user_sessions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash   TEXT    NOT NULL UNIQUE,
    ip           TEXT    NOT NULL DEFAULT '',
    user_agent   TEXT    NOT NULL DEFAULT '',
    created_at   TEXT    NOT NULL,
    last_seen_at TEXT    NOT NULL
);
CREATE INDEX idx_user_sessions_user ON user_sessions (user_id, last_seen_at);
