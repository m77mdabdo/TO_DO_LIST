-- Who follows a card or a whole board. board_id is kept on card watches too,
-- so "everyone watching this card or its board" is two index lookups.
CREATE TABLE watches (
    user_id    INT UNSIGNED NOT NULL,
    target     ENUM('board', 'card') NOT NULL,
    target_id  INT UNSIGNED NOT NULL,
    board_id   INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, target, target_id),
    KEY idx_watches_target (target, target_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(40)  NOT NULL,
    actor_id   INT UNSIGNED NULL,
    board_id   INT UNSIGNED NULL,
    card_id    INT UNSIGNED NULL,
    subject    VARCHAR(255) NOT NULL,
    data       TEXT NOT NULL,
    link       VARCHAR(255) NOT NULL,
    -- One notification per user per event: a second insert with the same key is refused.
    dedupe_key VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    read_at    DATETIME NULL,
    UNIQUE KEY uq_notifications_dedupe (user_id, dedupe_key),
    -- The bell: a user's newest first (keyset by id), and their unread count.
    KEY idx_notifications_user (user_id, id),
    KEY idx_notifications_unread (user_id, read_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_settings (
    user_id       INT UNSIGNED PRIMARY KEY,
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
