CREATE TABLE checklists (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    card_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(120) NOT NULL,
    position   VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_checklists_card (card_id, position),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT UNSIGNED NOT NULL,
    -- Copied from the checklist so a board's progress is one GROUP BY over this index.
    card_id      INT UNSIGNED NOT NULL,
    text         VARCHAR(500) NOT NULL,
    position     VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    done_at      DATETIME NULL,
    assignee_id  INT UNSIGNED NULL,
    due_at       DATETIME NULL,
    created_by   INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL,
    KEY idx_checklist_items_list (checklist_id, position),
    KEY idx_checklist_items_card (card_id, done_at),
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
