-- How a card looks: a cover (a color or one of its own images), a sticker,
-- and its kind: an ordinary card, a separator, or a link to another board.
ALTER TABLE cards
    ADD COLUMN cover_color VARCHAR(20) NULL,
    ADD COLUMN cover_attachment_id INT UNSIGNED NULL,
    ADD COLUMN cover_size VARCHAR(10) NOT NULL DEFAULT 'top',
    ADD COLUMN sticker VARCHAR(20) NULL,
    ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'card',
    ADD COLUMN link_board_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_cards_cover FOREIGN KEY (cover_attachment_id) REFERENCES attachments(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cards_link_board FOREIGN KEY (link_board_id) REFERENCES boards(id) ON DELETE SET NULL;
