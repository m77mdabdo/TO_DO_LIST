-- Which old task became which card, so the import runs once per task,
-- can be re-run safely, and every old record stays traceable.
CREATE TABLE legacy_task_map (
    task_id     INTEGER PRIMARY KEY REFERENCES tasks(id) ON DELETE CASCADE,
    card_id     INTEGER REFERENCES cards(id) ON DELETE SET NULL,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    imported_at TEXT    NOT NULL
);

CREATE INDEX idx_legacy_task_map_user ON legacy_task_map (user_id);

-- The "My Board" each user's old tasks go to, and its three lists.
CREATE TABLE legacy_boards (
    user_id       INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    board_id      INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    todo_list_id  INTEGER NOT NULL,
    doing_list_id INTEGER NOT NULL,
    done_list_id  INTEGER NOT NULL
);
