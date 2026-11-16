-- A template card stays on its list like any card; new cards can be made from it.
ALTER TABLE cards ADD COLUMN is_template INTEGER NOT NULL DEFAULT 0;
