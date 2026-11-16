-- Emoji reactions on comments: one of each emoji per person per comment (the primary key).
CREATE TABLE comment_reactions (
    comment_id INTEGER NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    emoji      TEXT    NOT NULL,
    created_at TEXT    NOT NULL,
    PRIMARY KEY (comment_id, user_id, emoji)
);

-- A file sent with a comment. It is also one of the card's attachments.
ALTER TABLE comments ADD COLUMN attachment_id INTEGER REFERENCES attachments(id) ON DELETE SET NULL;

-- Who is writing a comment on a card right now; rows older than a minute are dropped.
CREATE TABLE card_typing (
    card_id  INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    typed_at TEXT    NOT NULL,
    PRIMARY KEY (card_id, user_id)
);
