-- A picture behind a board. Stored like attachments: outside the web root, under a random name.
CREATE TABLE board_backgrounds (
    board_id    INTEGER PRIMARY KEY REFERENCES boards(id) ON DELETE CASCADE,
    stored_name TEXT    NOT NULL,
    mime        TEXT    NOT NULL,
    size        INTEGER NOT NULL,
    uploader_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at  TEXT    NOT NULL
);
