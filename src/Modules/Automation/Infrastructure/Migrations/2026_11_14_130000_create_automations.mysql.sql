CREATE TABLE automations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id     CHAR(26) NOT NULL,
    board_id      INT UNSIGNED NOT NULL,
    kind          ENUM('rule', 'card_button', 'board_button', 'schedule', 'due') NOT NULL,
    name          VARCHAR(120) NOT NULL,
    trigger_type  VARCHAR(40) NULL,
    trigger_value VARCHAR(40) NULL,
    scope_list_id INT UNSIGNED NULL,
    conditions    TEXT NOT NULL,
    actions       TEXT NOT NULL,
    schedule      VARCHAR(255) NOT NULL DEFAULT '{}',
    enabled       TINYINT(1) NOT NULL DEFAULT 1,
    failures      INT UNSIGNED NOT NULL DEFAULT 0,
    created_by    INT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL,
    last_run_at   DATETIME NULL,
    UNIQUE KEY uq_automations_public (public_id),
    -- "Which rules listen for this on this board?" on every card event.
    KEY idx_automations_board (board_id, kind, trigger_type),
    -- The scheduler's pass over all schedules and due-date automations.
    KEY idx_automations_kind (kind, enabled),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (scope_list_id) REFERENCES lists(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE automation_runs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    automation_id INT UNSIGNED NOT NULL,
    board_id      INT UNSIGNED NOT NULL,
    card_id       INT UNSIGNED NULL,
    actor_id      INT UNSIGNED NULL,
    status        ENUM('ok', 'failed', 'skipped') NOT NULL,
    detail        VARCHAR(500) NOT NULL DEFAULT '',
    -- Set for runs that must happen once only (a schedule's slot, a card's due date).
    dedupe_key    VARCHAR(120) NULL,
    created_at    DATETIME NOT NULL,
    UNIQUE KEY uq_automation_runs_dedupe (dedupe_key),
    KEY idx_automation_runs_board (board_id, id),
    KEY idx_automation_runs_created (created_at),
    FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
