-- Who may comment on and vote for the board's cards: off | members | everyone.
ALTER TABLE boards
    ADD COLUMN commenting ENUM('off', 'members', 'everyone') NOT NULL DEFAULT 'everyone',
    ADD COLUMN voting ENUM('off', 'members', 'everyone') NOT NULL DEFAULT 'members';
