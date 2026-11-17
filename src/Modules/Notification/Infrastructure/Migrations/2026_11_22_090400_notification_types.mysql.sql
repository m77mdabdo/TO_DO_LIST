-- Choices per kind of notification, as JSON lists of types:
--   muted_types: not shown in the app at all,
--   email_off_types: shown in the app but never emailed.
ALTER TABLE notification_settings
    ADD COLUMN muted_types VARCHAR(1000) NOT NULL DEFAULT '[]',
    ADD COLUMN email_off_types VARCHAR(1000) NOT NULL DEFAULT '[]';
