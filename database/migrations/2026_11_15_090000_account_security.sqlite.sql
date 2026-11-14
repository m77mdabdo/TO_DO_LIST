-- Account security: email confirmation, password reset links, a security
-- log per user, and the counters behind the request rate limits.

ALTER TABLE users ADD COLUMN email_verified_at TEXT;
-- Bumped when the password changes: every session that holds an older number is signed out.
ALTER TABLE users ADD COLUMN auth_version INTEGER NOT NULL DEFAULT 1;

-- Accounts made before this migration keep working as they did: they count as confirmed.
UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL;

-- One-time links sent by email. Only the SHA-256 of the secret is stored.
CREATE TABLE user_tokens (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    purpose    TEXT    NOT NULL CHECK (purpose IN ('password_reset', 'email_verification')),
    token_hash TEXT    NOT NULL UNIQUE,
    expires_at TEXT    NOT NULL,
    used_at    TEXT,
    created_at TEXT    NOT NULL
);
CREATE INDEX idx_user_tokens_user ON user_tokens (user_id, purpose);
CREATE INDEX idx_user_tokens_expiry ON user_tokens (expires_at);

CREATE TABLE security_events (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    type       TEXT    NOT NULL,
    ip         TEXT    NOT NULL DEFAULT '',
    detail     TEXT    NOT NULL DEFAULT '',
    created_at TEXT    NOT NULL
);
CREATE INDEX idx_security_events_user ON security_events (user_id, id);
CREATE INDEX idx_security_events_created ON security_events (created_at);

-- One row per counted request. bucket = SHA-256 of "rule|key", so no email or token is stored.
CREATE TABLE rate_limit_hits (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    bucket TEXT    NOT NULL,
    hit_at INTEGER NOT NULL
);
CREATE INDEX idx_rate_limit_hits_bucket ON rate_limit_hits (bucket, hit_at);
CREATE INDEX idx_rate_limit_hits_time ON rate_limit_hits (hit_at);

CREATE INDEX IF NOT EXISTS idx_attempts_time ON login_attempts (attempted_at);
