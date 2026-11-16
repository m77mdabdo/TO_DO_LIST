-- Groups that organize the boards of a workspace, in an order. A board is in one group or none.
CREATE TABLE board_groups (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED NOT NULL,
    name         VARCHAR(80) NOT NULL,
    position     INT NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL,
    KEY idx_board_groups_workspace (workspace_id, position),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE boards
    ADD COLUMN group_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_boards_group FOREIGN KEY (group_id) REFERENCES board_groups(id) ON DELETE SET NULL;

-- Lists one person folded away on a board (JSON list of list ids).
ALTER TABLE board_preferences ADD COLUMN collapsed_lists VARCHAR(2000) NOT NULL DEFAULT '[]';
