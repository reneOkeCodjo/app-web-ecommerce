-- Run on an existing database (init scripts only run on first volume create):
-- docker exec -i ecommerce-db mysql -padmin123 ecommerce < sql/04_add_product_promotion.sql
ALTER TABLE `ecommerce`.`product`
    ADD COLUMN `promotion_p` INT NOT NULL DEFAULT 0 AFTER `stock_p`;
