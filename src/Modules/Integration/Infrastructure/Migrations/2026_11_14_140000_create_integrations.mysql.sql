CREATE TABLE api_tokens (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id    CHAR(26) NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    name         VARCHAR(100) NOT NULL,
    -- SHA-256 of the secret; the secret itself is shown once and never stored.
    token_hash   CHAR(64) NOT NULL,
    scope        ENUM('read', 'write') NOT NULL,
    board_ids    TEXT NOT NULL,
    expires_at   DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at   DATETIME NOT NULL,
    revoked_at   DATETIME NULL,
    UNIQUE KEY uq_api_tokens_public (public_id),
    UNIQUE KEY uq_api_tokens_hash (token_hash),
    KEY idx_api_tokens_user (user_id, id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhooks (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id  CHAR(26) NOT NULL,
    board_id   INT UNSIGNED NOT NULL,
    url        VARCHAR(2000) NOT NULL,
    secret     CHAR(64) NOT NULL,
    events     TEXT NOT NULL,
    enabled    TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_webhooks_public (public_id),
    KEY idx_webhooks_board (board_id, enabled),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_deliveries (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    webhook_id      INT UNSIGNED NOT NULL,
    event           VARCHAR(40) NOT NULL,
    payload         TEXT NOT NULL,
    status          ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts        INT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NOT NULL,
    response_code   SMALLINT UNSIGNED NULL,
    error           VARCHAR(500) NOT NULL DEFAULT '',
    created_at      DATETIME NOT NULL,
    delivered_at    DATETIME NULL,
    -- The sender's queue: what is due now.
    KEY idx_webhook_deliveries_queue (status, next_attempt_at),
    KEY idx_webhook_deliveries_hook (webhook_id, id),
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
