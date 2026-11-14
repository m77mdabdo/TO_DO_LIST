-- Sensitive changes in a workspace: invitations, roles, removals. Rows are
-- only ever added, never changed. Names and emails are copied in, so the
-- log still reads right after people leave.
CREATE TABLE audit_log (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    actor_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action       TEXT    NOT NULL,
    target       TEXT    NOT NULL DEFAULT '',
    details      TEXT    NOT NULL DEFAULT '{}',
    created_at   TEXT    NOT NULL
);
-- A workspace's log, newest first, paged by id (keyset).
CREATE INDEX idx_audit_workspace ON audit_log (workspace_id, id);
