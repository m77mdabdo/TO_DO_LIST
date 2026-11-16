-- Emoji reactions on comments: one of each emoji per person per comment (the primary key).
CREATE TABLE comment_reactions (
    comment_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    emoji      VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (comment_id, user_id, emoji),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A file sent with a comment. It is also one of the card's attachments.
ALTER TABLE comments ADD COLUMN attachment_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_comments_attachment FOREIGN KEY (attachment_id) REFERENCES attachments(id) ON DELETE SET NULL;

-- Who is writing a comment on a card right now; rows older than a minute are dropped.
CREATE TABLE card_typing (
    card_id  INT UNSIGNED NOT NULL,
    user_id  INT UNSIGNED NOT NULL,
    typed_at DATETIME NOT NULL,
    PRIMARY KEY (card_id, user_id),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
