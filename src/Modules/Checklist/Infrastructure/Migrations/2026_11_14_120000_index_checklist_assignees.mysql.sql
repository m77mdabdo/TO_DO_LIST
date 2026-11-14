-- "My tasks": the open items given to a person.
CREATE INDEX idx_checklist_items_assignee ON checklist_items (assignee_id, done_at);
