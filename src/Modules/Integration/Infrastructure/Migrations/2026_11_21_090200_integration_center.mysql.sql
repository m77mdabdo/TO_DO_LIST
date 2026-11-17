-- Ready-made integrations of a workspace (Slack first). The secret part of the
-- settings (e.g. the Slack webhook address) is encrypted with APP_KEY; only its
-- last characters are kept in clear to show which one is set.
--   status: active, paused, disconnected (secret wiped, history kept)
CREATE TABLE integrations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id       CHAR(26)     NOT NULL,
    workspace_id    INT UNSIGNED NOT NULL,
    type            VARCHAR(30)  NOT NULL,
    name            VARCHAR(100) NOT NULL,
    secret          TEXT NULL,
    secret_hint     VARCHAR(20)  NOT NULL DEFAULT '',
    board_ids       TEXT NOT NULL,
    events          TEXT NOT NULL,
    status          VARCHAR(15)  NOT NULL DEFAULT 'active',
    connected_by    INT UNSIGNED NULL,
    last_success_at DATETIME NULL,
    last_error      VARCHAR(500) NOT NULL DEFAULT '',
    last_error_at   DATETIME NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    UNIQUE KEY uq_integrations_public (public_id),
    KEY idx_integrations_workspace (workspace_id, status),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (connected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who changed what on an integration (connected, settings, rotated, paused, disconnected, tested).
CREATE TABLE integration_log (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    integration_id INT UNSIGNED NOT NULL,
    actor_id       INT UNSIGNED NULL,
    action         VARCHAR(30)  NOT NULL,
    details        VARCHAR(500) NOT NULL DEFAULT '',
    created_at     DATETIME NOT NULL,
    KEY idx_integration_log (integration_id, id),
    FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages waiting to be sent by bin/webhooks.php, with retries.
CREATE TABLE integration_deliveries (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    integration_id  INT UNSIGNED NOT NULL,
    board_id        INT UNSIGNED NULL,
    event           VARCHAR(40)  NOT NULL,
    payload         TEXT NOT NULL,
    status          VARCHAR(10)  NOT NULL DEFAULT 'pending',
    attempts        INT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NOT NULL,
    response_code   INT NULL,
    error           VARCHAR(500) NOT NULL DEFAULT '',
    created_at      DATETIME NOT NULL,
    delivered_at    DATETIME NULL,
    KEY idx_integration_deliveries_queue (status, next_attempt_at),
    KEY idx_integration_deliveries_one (integration_id, id),
    FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
