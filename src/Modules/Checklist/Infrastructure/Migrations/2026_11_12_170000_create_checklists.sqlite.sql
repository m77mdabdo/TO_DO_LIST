CREATE TABLE checklists (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    card_id    INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    title      TEXT    NOT NULL,
    position   TEXT    NOT NULL,
    created_by INTEGER NOT NULL REFERENCES users(id),
    created_at TEXT    NOT NULL
);

CREATE INDEX idx_checklists_card ON checklists (card_id, position);

CREATE TABLE checklist_items (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    checklist_id INTEGER NOT NULL REFERENCES checklists(id) ON DELETE CASCADE,
    -- Copied from the checklist so a board's progress is one GROUP BY over this index.
    card_id      INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    text         TEXT    NOT NULL,
    position     TEXT    NOT NULL,
    done_at      TEXT,
    assignee_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    due_at       TEXT,
    created_by   INTEGER NOT NULL REFERENCES users(id),
    created_at   TEXT    NOT NULL
);

CREATE INDEX idx_checklist_items_list ON checklist_items (checklist_id, position);
CREATE INDEX idx_checklist_items_card ON checklist_items (card_id, done_at);
