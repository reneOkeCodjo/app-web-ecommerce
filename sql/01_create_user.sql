CREATE TABLE `ecommerce`.`compte` (
    `compte_id` INT NOT NULL AUTO_INCREMENT,
    `compte_name` TEXT NOT NULL,
    PRIMARY KEY (`compte_id`)
) ENGINE = InnoDB;

CREATE TABLE `ecommerce`.`user` (
    `user_id` INT NOT NULL AUTO_INCREMENT,
    `user_login` TEXT NOT NULL,
    `user_password` LONGTEXT NOT NULL,
    `user_compte_id` INT NOT NULL,
    `user_mail` TEXT NOT NULL,
    `user_date_new` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_date_login` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_user_compte` FOREIGN KEY (`user_compte_id`) REFERENCES `ecommerce`.`compte` (`compte_id`)
) ENGINE = InnoDB;

CREATE TABLE `ecommerce`.`product` (
    `id_p` INT NOT NULL AUTO_INCREMENT,
    `type_p` VARCHAR(100) NOT NULL,
    `designation_p` VARCHAR(255) NOT NULL,
    `prix_ht` DECIMAL(10, 2) NOT NULL,
    `date_in` DATE NOT NULL,
    `times_in` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `stock_p` INT DEFAULT 0,
    `promotion_p` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_p`)
) ENGINE = InnoDB;
