-- Personal settings, two-step sign-in, and the list of signed-in devices.

ALTER TABLE users
    -- The language the person chose; applied every time they sign in. NULL = the app default.
    ADD COLUMN locale VARCHAR(5) NULL,
    -- Two-step sign-in (TOTP). The secret is encrypted with APP_KEY; recovery
    -- codes are kept only as SHA-256 hashes (a JSON list), each usable once.
    ADD COLUMN two_factor_secret VARCHAR(255) NULL,
    ADD COLUMN two_factor_recovery TEXT NULL,
    ADD COLUMN two_factor_enabled_at DATETIME NULL,
    -- Set when the person deletes their account: the row stays (their old work
    -- keeps an author) but holds no personal data and can't sign in.
    ADD COLUMN deleted_at DATETIME NULL;

-- One row per signed-in browser. The session holds a secret; only its hash is
-- stored. Deleting a row signs that device out on its next request.
CREATE TABLE user_sessions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    token_hash   CHAR(64)     NOT NULL,
    ip           VARCHAR(45)  NOT NULL DEFAULT '',
    user_agent   VARCHAR(255) NOT NULL DEFAULT '',
    created_at   DATETIME     NOT NULL,
    last_seen_at DATETIME     NOT NULL,
    UNIQUE KEY uq_user_sessions_token (token_hash),
    KEY idx_user_sessions_user (user_id, last_seen_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
