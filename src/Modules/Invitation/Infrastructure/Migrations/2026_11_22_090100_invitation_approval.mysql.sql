-- An invitation from a member (not an owner or admin) can wait for approval:
-- no email goes out and no working link exists until someone approves it.
ALTER TABLE invitations ADD COLUMN awaiting_approval TINYINT(1) NOT NULL DEFAULT 0;
