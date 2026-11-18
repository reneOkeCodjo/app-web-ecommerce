-- Migration: Persist user carts in MySQL
-- Date: 2026-11-17

CREATE TABLE IF NOT EXISTS `carts` (
    `cart_id` INT NOT NULL AUTO_INCREMENT,
    `cart_user_id` INT NOT NULL,
    `cart_date_new` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cart_date_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`cart_id`),
    UNIQUE KEY `uq_cart_user` (`cart_user_id`),
    CONSTRAINT `fk_cart_user`
        FOREIGN KEY (`cart_user_id`) REFERENCES `users` (`user_id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cart_items` (
    `cart_item_id` INT NOT NULL AUTO_INCREMENT,
    `cart_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `cart_item_quantity` INT NOT NULL,
    PRIMARY KEY (`cart_item_id`),
    UNIQUE KEY `uq_cart_product` (`cart_id`, `product_id`),
    CONSTRAINT `fk_cart_item_cart`
        FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cart_item_product`
        FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`)
        ON DELETE CASCADE,
    CONSTRAINT `chk_cart_item_quantity` CHECK (`cart_item_quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
