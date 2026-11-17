-- Suspending a member keeps them in the list (and their work), but takes away
-- every access until an owner or admin turns them back on.
ALTER TABLE workspace_members ADD COLUMN suspended_at DATETIME NULL;
ALTER TABLE workspace_members ADD COLUMN suspended_by INT UNSIGNED NULL;

-- Workspace policies, set by owners.
--   invite_policy: admins (only owners and admins invite) or members (full members may too)
--   invite_role: the role a member's invitation gives (member or guest)
--   invite_approval: 1 = invitations from members wait for an owner or admin before they are sent
--   public_links: 1 = board managers may share a board read-only through a secret link
ALTER TABLE workspaces ADD COLUMN invite_policy VARCHAR(10) NOT NULL DEFAULT 'admins';
ALTER TABLE workspaces ADD COLUMN invite_role VARCHAR(10) NOT NULL DEFAULT 'member';
ALTER TABLE workspaces ADD COLUMN invite_approval TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE workspaces ADD COLUMN public_links TINYINT(1) NOT NULL DEFAULT 0;
