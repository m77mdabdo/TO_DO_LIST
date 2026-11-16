-- A board's custom fields. Options (for "select") are JSON: [{"id": "o1", "name": "High"}].
CREATE TABLE custom_fields (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id   INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    name       TEXT    NOT NULL,
    type       TEXT    NOT NULL,
    options    TEXT    NOT NULL DEFAULT '[]',
    position   INTEGER NOT NULL DEFAULT 0,
    on_card    INTEGER NOT NULL DEFAULT 1,
    created_at TEXT    NOT NULL
);

CREATE INDEX idx_custom_fields_board ON custom_fields (board_id, position);

-- One value per card and field; a field with no value has no row.
CREATE TABLE card_field_values (
    card_id    INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    field_id   INTEGER NOT NULL REFERENCES custom_fields(id) ON DELETE CASCADE,
    value      TEXT    NOT NULL,
    updated_at TEXT    NOT NULL,
    PRIMARY KEY (card_id, field_id)
);

CREATE INDEX idx_card_field_values_field ON card_field_values (field_id);
