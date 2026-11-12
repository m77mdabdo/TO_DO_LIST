-- Only a SHA-256 hash of the link's secret is kept: a leaked database can't
-- be used to join anyone's workspace.
CREATE TABLE invitations (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id    CHAR(26)     NOT NULL,
    workspace_id INT UNSIGNED NOT NULL,
    email        VARCHAR(190) NOT NULL,
    role         ENUM('owner', 'admin', 'member', 'guest') NOT NULL,
    board_role   ENUM('manager', 'member', 'viewer') NOT NULL,
    token_hash   CHAR(64)     NOT NULL,
    invited_by   INT UNSIGNED NOT NULL,
    created_at   DATETIME     NOT NULL,
    sent_at      DATETIME     NOT NULL,
    expires_at   DATETIME     NOT NULL,
    accepted_at  DATETIME     NULL,
    accepted_by  INT UNSIGNED NULL,
    revoked_at   DATETIME     NULL,
    UNIQUE KEY uq_invitations_public (public_id),
    UNIQUE KEY uq_invitations_token (token_hash),
    -- Open invitations of a workspace, and "is this email already invited?".
    KEY idx_invitations_workspace (workspace_id, email),
    -- How many invitations someone sent lately (rate limit).
    KEY idx_invitations_sender (invited_by, sent_at),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id),
    FOREIGN KEY (accepted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The boards an invitation adds the person to.
CREATE TABLE invitation_boards (
    invitation_id INT UNSIGNED NOT NULL,
    board_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (invitation_id, board_id),
    FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
