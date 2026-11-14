CREATE TABLE automations (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id     TEXT    NOT NULL UNIQUE,
    board_id      INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    kind          TEXT    NOT NULL CHECK (kind IN ('rule', 'card_button', 'board_button', 'schedule', 'due')),
    name          TEXT    NOT NULL,
    trigger_type  TEXT,
    trigger_value TEXT,
    scope_list_id INTEGER REFERENCES lists(id) ON DELETE SET NULL,
    conditions    TEXT    NOT NULL DEFAULT '[]',
    actions       TEXT    NOT NULL DEFAULT '[]',
    schedule      TEXT    NOT NULL DEFAULT '{}',
    enabled       INTEGER NOT NULL DEFAULT 1,
    failures      INTEGER NOT NULL DEFAULT 0,
    created_by    INTEGER NOT NULL REFERENCES users(id),
    created_at    TEXT    NOT NULL,
    last_run_at   TEXT
);

-- "Which rules listen for this on this board?" on every card event.
CREATE INDEX idx_automations_board ON automations (board_id, kind, trigger_type);
-- The scheduler's pass over all schedules and due-date automations.
CREATE INDEX idx_automations_kind ON automations (kind, enabled);

CREATE TABLE automation_runs (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    automation_id INTEGER NOT NULL REFERENCES automations(id) ON DELETE CASCADE,
    board_id      INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    card_id       INTEGER REFERENCES cards(id) ON DELETE SET NULL,
    actor_id      INTEGER REFERENCES users(id) ON DELETE SET NULL,
    status        TEXT    NOT NULL CHECK (status IN ('ok', 'failed', 'skipped')),
    detail        TEXT    NOT NULL DEFAULT '',
    -- Set for runs that must happen once only (a schedule's slot, a card's due date).
    dedupe_key    TEXT    UNIQUE,
    created_at    TEXT    NOT NULL
);

CREATE INDEX idx_automation_runs_board ON automation_runs (board_id, id);
CREATE INDEX idx_automation_runs_created ON automation_runs (created_at);
