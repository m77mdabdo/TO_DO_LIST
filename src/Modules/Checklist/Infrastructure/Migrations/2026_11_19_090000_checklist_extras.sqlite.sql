-- An item turned into a card keeps a link to that card.
ALTER TABLE checklist_items ADD COLUMN card_link_id INTEGER REFERENCES cards(id) ON DELETE SET NULL;

-- Checklists whose ticked items one person chose to hide. Only that person is affected.
CREATE TABLE checklist_hidden (
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    checklist_id INTEGER NOT NULL REFERENCES checklists(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, checklist_id)
);
