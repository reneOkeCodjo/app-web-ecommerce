-- Migration:  Create products table
-- Date: 2026-10-11



create table if not exists `product` (
    `product_id` int not null auto_increment,
    `product_type` varchar(100) not null,
    `product_designation` varchar(255) not null,
    `product_price_ht` decimal(10,2) not null,
    `product_stock` int not null default 0,
    `product_date_in` Date not null,
    `product_date_new` timestamp not null default current_timestamp,
    primary key (`product_id`)
) engine=innodb 
default charset=utf8mb4
 collate=utf8mb4_unicode_ci;