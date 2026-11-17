-- Imports (CSV into a board, a Trello export into a new board). An upload is
-- read once into a file under storage/imports and waits here as "pending"
-- until the person checks the preview and runs it, or cancels it.
--   kind: csv, trello    status: pending, done, cancelled
--   source_key: the Trello board id or the file's SHA-256, to spot a second import of the same thing
CREATE TABLE imports (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id    CHAR(26)     NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    workspace_id INT UNSIGNED NOT NULL,
    board_id     INT UNSIGNED NULL,
    kind         VARCHAR(10)  NOT NULL,
    status       VARCHAR(10)  NOT NULL DEFAULT 'pending',
    file_name    VARCHAR(255) NOT NULL,
    source_key   VARCHAR(64)  NOT NULL,
    summary      TEXT NULL,
    created_at   DATETIME NOT NULL,
    finished_at  DATETIME NULL,
    UNIQUE KEY uq_imports_public (public_id),
    KEY idx_imports_source (workspace_id, kind, source_key),
    KEY idx_imports_user (user_id, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
