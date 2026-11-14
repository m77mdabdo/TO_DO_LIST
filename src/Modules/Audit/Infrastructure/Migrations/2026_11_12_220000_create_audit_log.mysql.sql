-- Sensitive changes in a workspace: invitations, roles, removals. Rows are
-- only ever added, never changed. Names and emails are copied in, so the
-- log still reads right after people leave.
CREATE TABLE audit_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED NOT NULL,
    actor_id     INT UNSIGNED NULL,
    action       VARCHAR(40)  NOT NULL,
    target       VARCHAR(190) NOT NULL DEFAULT '',
    details      TEXT NOT NULL,
    created_at   DATETIME NOT NULL,
    -- A workspace's log, newest first, paged by id (keyset).
    KEY idx_audit_workspace (workspace_id, id),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
