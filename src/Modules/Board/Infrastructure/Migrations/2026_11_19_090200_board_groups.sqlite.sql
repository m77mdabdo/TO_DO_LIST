-- Groups that organize the boards of a workspace, in an order. A board is in one group or none.
CREATE TABLE board_groups (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name         TEXT    NOT NULL,
    position     INTEGER NOT NULL DEFAULT 0,
    created_at   TEXT    NOT NULL
);
CREATE INDEX idx_board_groups_workspace ON board_groups (workspace_id, position);
ALTER TABLE boards ADD COLUMN group_id INTEGER REFERENCES board_groups(id) ON DELETE SET NULL;

-- Lists one person folded away on a board (JSON list of list ids).
ALTER TABLE board_preferences ADD COLUMN collapsed_lists TEXT NOT NULL DEFAULT '[]';
