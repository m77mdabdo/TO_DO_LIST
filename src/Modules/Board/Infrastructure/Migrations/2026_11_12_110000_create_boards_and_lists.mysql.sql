CREATE TABLE boards (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id    CHAR(26)     NOT NULL UNIQUE,
    workspace_id INT UNSIGNED NOT NULL,
    name         VARCHAR(100) NOT NULL,
    description  TEXT NULL,
    color        VARCHAR(20)  NOT NULL DEFAULT 'violet',
    visibility   ENUM('private', 'workspace') NOT NULL DEFAULT 'private',
    created_by   INT UNSIGNED NOT NULL,
    archived_at  DATETIME NULL,
    created_at   DATETIME NOT NULL,
    updated_at   DATETIME NOT NULL,
    KEY idx_boards_workspace (workspace_id, archived_at, name),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lists (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    board_id    INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    -- Binary collation: ordering keys must compare byte by byte ("a" > "Z").
    position    VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    archived_at DATETIME NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    KEY idx_lists_board_position (board_id, position),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
