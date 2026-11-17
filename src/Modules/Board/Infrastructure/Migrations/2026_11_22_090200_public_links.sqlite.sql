-- A secret, read-only link to one board. Only a SHA-256 hash of the secret is
-- kept; a new link replaces the old one, and turning it off deletes the row.
-- It works only while the workspace rules allow public links.
CREATE TABLE board_public_links (
    board_id   INTEGER PRIMARY KEY REFERENCES boards(id) ON DELETE CASCADE,
    token_hash TEXT    NOT NULL UNIQUE,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT    NOT NULL
);
