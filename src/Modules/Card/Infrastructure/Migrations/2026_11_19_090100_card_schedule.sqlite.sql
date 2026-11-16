-- Repeating due dates and reminders on the card itself.
--   recurrence: NULL, daily, weekdays, weekly, monthly, yearly. Completing the card moves the dates on.
--   remind: NULL (the usual "due soon" and "overdue"), none, or days before the due date (0, 1, 2, 7).
--   remind_hour: the hour of that day, in the app's time zone.
ALTER TABLE cards ADD COLUMN recurrence TEXT;
ALTER TABLE cards ADD COLUMN remind TEXT;
ALTER TABLE cards ADD COLUMN remind_hour INTEGER NOT NULL DEFAULT 9;

-- A card that is copied on a schedule (a separate choice from repeating its due date).
--   frequency: daily, weekly (on the weekdays in "weekdays", 0 = Sunday), monthly (on "month_day").
CREATE TABLE card_repeats (
    card_id      INTEGER PRIMARY KEY REFERENCES cards(id) ON DELETE CASCADE,
    board_id     INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    list_id      INTEGER NOT NULL REFERENCES lists(id) ON DELETE CASCADE,
    frequency    TEXT    NOT NULL,
    weekdays     TEXT    NOT NULL DEFAULT '',
    month_day    INTEGER NOT NULL DEFAULT 1,
    hour         INTEGER NOT NULL DEFAULT 6,
    next_at      TEXT    NOT NULL,
    last_run_at  TEXT,
    last_result  TEXT,
    created_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at   TEXT    NOT NULL
);
CREATE INDEX idx_card_repeats_next ON card_repeats (next_at);

-- One row per copy made: the primary key stops a second copy for the same time,
-- even if the scheduler runs twice at once or is retried.
CREATE TABLE card_repeat_runs (
    card_id     INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    slot        TEXT    NOT NULL,
    new_card_id INTEGER REFERENCES cards(id) ON DELETE SET NULL,
    created_at  TEXT    NOT NULL,
    PRIMARY KEY (card_id, slot)
);
