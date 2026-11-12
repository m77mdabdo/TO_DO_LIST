CREATE TABLE card_members (
    card_id    INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TEXT    NOT NULL,
    PRIMARY KEY (card_id, user_id)
);

-- "Cards assigned to me" without scanning every card.
CREATE INDEX idx_card_members_user ON card_members (user_id);
