-- Account security: email confirmation, password reset links, a security
-- log per user, and the counters behind the request rate limits.

ALTER TABLE users
    ADD COLUMN email_verified_at DATETIME NULL,
    -- Bumped when the password changes: every session that holds an older number is signed out.
    ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1;

-- Accounts made before this migration keep working as they did: they count as confirmed.
UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL;

-- One-time links sent by email. Only the SHA-256 of the secret is stored.
CREATE TABLE user_tokens (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    purpose    ENUM('password_reset', 'email_verification') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_user_tokens_hash (token_hash),
    KEY idx_user_tokens_user (user_id, purpose),
    KEY idx_user_tokens_expiry (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE security_events (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    type       VARCHAR(40) NOT NULL,
    ip         VARCHAR(45) NOT NULL DEFAULT '',
    detail     VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    KEY idx_security_events_user (user_id, id),
    KEY idx_security_events_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per counted request. bucket = SHA-256 of "rule|key", so no email or token is stored.
CREATE TABLE rate_limit_hits (
    id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bucket CHAR(64) NOT NULL,
    hit_at INT UNSIGNED NOT NULL,
    KEY idx_rate_limit_hits_bucket (bucket, hit_at),
    KEY idx_rate_limit_hits_time (hit_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_attempts_time ON login_attempts (attempted_at);
