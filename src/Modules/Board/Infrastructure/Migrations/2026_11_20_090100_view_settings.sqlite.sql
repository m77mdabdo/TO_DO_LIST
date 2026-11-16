-- How one person set up a view of a board (the table's columns, sort and grouping;
-- the dashboard's widgets). Nobody else's view changes.
CREATE TABLE view_settings (
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    board_id   INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    view       TEXT    NOT NULL,
    settings   TEXT    NOT NULL,
    updated_at TEXT    NOT NULL,
    PRIMARY KEY (user_id, board_id, view)
);
