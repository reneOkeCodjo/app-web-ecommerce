-- Migration: Add optional product image path/URL
-- Date: 2026-10-13
-- Order: 006 (requires 004_create_products_table)

ALTER TABLE product
ADD COLUMN product_image VARCHAR(255) NULL DEFAULT NULL;
