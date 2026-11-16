-- How a card looks: a cover (a color or one of its own images), a sticker,
-- and its kind: an ordinary card, a separator, or a link to another board.
ALTER TABLE cards ADD COLUMN cover_color TEXT;
ALTER TABLE cards ADD COLUMN cover_attachment_id INTEGER REFERENCES attachments(id) ON DELETE SET NULL;
ALTER TABLE cards ADD COLUMN cover_size TEXT NOT NULL DEFAULT 'top';
ALTER TABLE cards ADD COLUMN sticker TEXT;
ALTER TABLE cards ADD COLUMN kind TEXT NOT NULL DEFAULT 'card';
ALTER TABLE cards ADD COLUMN link_board_id INTEGER REFERENCES boards(id) ON DELETE SET NULL;
