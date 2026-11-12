CREATE TABLE board_members (
    board_id INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role     TEXT    NOT NULL CHECK (role IN ('manager', 'member', 'viewer')),
    added_at TEXT    NOT NULL,
    PRIMARY KEY (board_id, user_id)
);

-- "Which boards was this user added to?"
CREATE INDEX idx_board_members_user ON board_members (user_id);
