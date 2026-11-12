CREATE TABLE IF NOT EXISTS users (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    email      TEXT    NOT NULL UNIQUE,
    password   TEXT    NOT NULL,
    created_at TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS tasks (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title        TEXT    NOT NULL,
    notes        TEXT,
    status       TEXT    NOT NULL DEFAULT 'todo'   CHECK (status IN ('todo', 'doing', 'done')),
    priority     TEXT    NOT NULL DEFAULT 'normal' CHECK (priority IN ('low', 'normal', 'high')),
    category     TEXT,
    due_date     TEXT,
    completed_at TEXT,
    created_at   TEXT    NOT NULL,
    updated_at   TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_tasks_user_status ON tasks (user_id, status);

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    ip           TEXT    NOT NULL,
    email        TEXT    NOT NULL,
    attempted_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_attempts_ip ON login_attempts (ip, attempted_at);
