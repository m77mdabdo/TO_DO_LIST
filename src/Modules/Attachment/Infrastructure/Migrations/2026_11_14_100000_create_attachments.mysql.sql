CREATE TABLE attachments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id   CHAR(26) NOT NULL,
    card_id     INT UNSIGNED NOT NULL,
    board_id    INT UNSIGNED NOT NULL,
    uploader_id INT UNSIGNED NOT NULL,
    kind        ENUM('file', 'link') NOT NULL,
    name        VARCHAR(190) NOT NULL,
    url         VARCHAR(2000) NULL,
    mime        VARCHAR(120) NULL,
    size        INT UNSIGNED NOT NULL DEFAULT 0,
    stored_name VARCHAR(80) NULL,
    created_at  DATETIME NOT NULL,
    UNIQUE KEY uq_attachments_public (public_id),
    UNIQUE KEY uq_attachments_stored (stored_name),
    -- A card's attachments, newest first; counts per card for the board tiles.
    KEY idx_attachments_card (card_id, id),
    -- Bytes per board, for the quota.
    KEY idx_attachments_board (board_id),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (uploader_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
