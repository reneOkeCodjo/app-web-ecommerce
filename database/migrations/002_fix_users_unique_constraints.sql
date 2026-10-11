-- Migration: Allow many users per compte type; unique login/email
-- Date: 2026-10-11

ALTER TABLE `users`
    DROP FOREIGN KEY `fk_user_compte`;

ALTER TABLE `users`
    DROP INDEX `cle-etrangere`;

ALTER TABLE `users`
    MODIFY `user_login` VARCHAR(255) NOT NULL,
    MODIFY `user_mail` VARCHAR(255) NOT NULL;

ALTER TABLE `users`
    ADD UNIQUE KEY `uq_user_login` (`user_login`),
    ADD UNIQUE KEY `uq_user_mail` (`user_mail`),
    ADD KEY `idx_user_compte_id` (`user_compte_id`);

ALTER TABLE `users`
    ADD CONSTRAINT `fk_user_compte`
        FOREIGN KEY (`user_compte_id`) REFERENCES `compte` (`compte_id`);
