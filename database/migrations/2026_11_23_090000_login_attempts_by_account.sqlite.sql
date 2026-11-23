-- Login failures are now also counted per account (LoginThrottle), so look them up by email.
CREATE INDEX IF NOT EXISTS idx_attempts_email ON login_attempts (email, attempted_at);
