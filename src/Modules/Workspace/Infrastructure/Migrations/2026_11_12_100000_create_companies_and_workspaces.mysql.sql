CREATE TABLE companies (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id  CHAR(26)     NOT NULL UNIQUE,
    name       VARCHAR(100) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workspaces (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id   CHAR(26)     NOT NULL UNIQUE,
    company_id  INT UNSIGNED NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at  DATETIME     NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workspace_members (
    workspace_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    role         ENUM('owner', 'admin', 'member', 'guest') NOT NULL,
    joined_at    DATETIME NOT NULL,
    -- Set when the user switches to this workspace, so the next sign-in opens it again.
    last_opened_at DATETIME NULL,
    PRIMARY KEY (workspace_id, user_id),
    KEY idx_workspace_members_user (user_id, joined_at),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
