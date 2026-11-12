-- Who may comment on and vote for the board's cards: off | members | everyone.
ALTER TABLE boards ADD COLUMN commenting TEXT NOT NULL DEFAULT 'everyone';
ALTER TABLE boards ADD COLUMN voting TEXT NOT NULL DEFAULT 'members';
