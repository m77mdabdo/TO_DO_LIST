CREATE TABLE comments (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    card_id    INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    board_id   INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    author_id  INTEGER NOT NULL REFERENCES users(id),
    body       TEXT    NOT NULL,
    created_at TEXT    NOT NULL,
    edited_at  TEXT
);

-- A card's comments, newest first, paged by id (keyset): one index range per page.
CREATE INDEX idx_comments_card ON comments (card_id, id);

-- Who was @mentioned, kept for notifications.
CREATE TABLE comment_mentions (
    comment_id INTEGER NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    PRIMARY KEY (comment_id, user_id)
);

CREATE INDEX idx_comment_mentions_user ON comment_mentions (user_id);

-- One vote per person per card: the primary key makes a second one impossible.
CREATE TABLE card_votes (
    card_id    INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TEXT    NOT NULL,
    PRIMARY KEY (card_id, user_id)
);
