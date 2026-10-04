-- Migration: Create compte and users tables
-- Date: 2026-10-04

-- Table des types de compte (client, admin, vendeur, etc.)
CREATE TABLE IF NOT EXISTS `compte` (
    `compte_id` INT NOT NULL AUTO_INCREMENT,
    `compte_type` VARCHAR(50) NOT NULL,
    `compte_description` TEXT,
    `compte_date_new` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`compte_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS `user` (
    `user_id` INT NOT NULL AUTO_INCREMENT,
    `user_login` TEXT NOT NULL,
    `user_password` LONGTEXT NOT NULL,
    `user_compte_id` INT NOT NULL,
    `user_mail` TEXT NOT NULL,
    `user_date_new` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_date_login` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `cle-etrangere` (`user_compte_id`),
    CONSTRAINT `fk_user_compte` FOREIGN KEY (`user_compte_id`) REFERENCES `compte`(`compte_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données par défaut pour les types de compte
INSERT INTO `compte` (`compte_type`, `compte_description`) VALUES
('client', 'Compte client standard'),
('vendeur', 'Compte vendeur'),
('admin', 'Compte administrateur');
