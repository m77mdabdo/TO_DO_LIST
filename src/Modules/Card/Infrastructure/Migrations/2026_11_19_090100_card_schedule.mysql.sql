-- Repeating due dates and reminders on the card itself.
--   recurrence: NULL, daily, weekdays, weekly, monthly, yearly. Completing the card moves the dates on.
--   remind: NULL (the usual "due soon" and "overdue"), none, or days before the due date (0, 1, 2, 7).
--   remind_hour: the hour of that day, in the app's time zone.
ALTER TABLE cards
    ADD COLUMN recurrence VARCHAR(20) NULL,
    ADD COLUMN remind VARCHAR(10) NULL,
    ADD COLUMN remind_hour TINYINT UNSIGNED NOT NULL DEFAULT 9;

-- A card that is copied on a schedule (a separate choice from repeating its due date).
--   frequency: daily, weekly (on the weekdays in "weekdays", 0 = Sunday), monthly (on "month_day").
CREATE TABLE card_repeats (
    card_id      INT UNSIGNED NOT NULL PRIMARY KEY,
    board_id     INT UNSIGNED NOT NULL,
    list_id      INT UNSIGNED NOT NULL,
    frequency    VARCHAR(10) NOT NULL,
    weekdays     VARCHAR(20) NOT NULL DEFAULT '',
    month_day    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    hour         TINYINT UNSIGNED NOT NULL DEFAULT 6,
    next_at      DATETIME NOT NULL,
    last_run_at  DATETIME NULL,
    last_result  VARCHAR(255) NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL,
    KEY idx_card_repeats_next (next_at),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (list_id) REFERENCES lists(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per copy made: the primary key stops a second copy for the same time,
-- even if the scheduler runs twice at once or is retried.
CREATE TABLE card_repeat_runs (
    card_id     INT UNSIGNED NOT NULL,
    slot        VARCHAR(20) NOT NULL,
    new_card_id INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (card_id, slot),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (new_card_id) REFERENCES cards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
