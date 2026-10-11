-- Migration: Seed default admin user (Docker MySQL init)
-- Date: 2026-10-11
--
-- Default credentials (dev only — change in production):
--   login:    admin
--   password: Admin123!
-- Password hash generated with PHP password_hash(..., PASSWORD_DEFAULT).

INSERT INTO `users` (`user_login`, `user_mail`, `user_password`, `user_compte_id`)
SELECT
    'admin',
    'admin@example.com',
    '$2y$12$rbBtVKZrY7vnQY9xq0U8guxfnqcJWR5E08aSQl9OIAWc15S3itPvi',
    `compte_id`
FROM `compte`
WHERE `compte_type` = 'admin'
  AND NOT EXISTS (
      SELECT 1 FROM `users` WHERE `user_login` = 'admin'
  )
LIMIT 1;
