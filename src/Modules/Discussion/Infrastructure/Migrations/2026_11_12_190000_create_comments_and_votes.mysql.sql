CREATE TABLE comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    card_id    INT UNSIGNED NOT NULL,
    board_id   INT UNSIGNED NOT NULL,
    author_id  INT UNSIGNED NOT NULL,
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    edited_at  DATETIME NULL,
    -- A card's comments, newest first, paged by id (keyset): one index range per page.
    KEY idx_comments_card (card_id, id),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comment_mentions (
    comment_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (comment_id, user_id),
    KEY idx_comment_mentions_user (user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One vote per person per card: the primary key makes a second one impossible.
CREATE TABLE card_votes (
    card_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (card_id, user_id),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
