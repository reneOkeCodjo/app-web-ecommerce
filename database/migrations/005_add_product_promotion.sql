-- Migration: Add optional product promotion percentage
-- Date: 2026-10-11
-- Order: 005 (requires 004_create_products_table)

-- Ajout d'un pourcentage de promotion facultatif.
ALTER TABLE product
ADD COLUMN ppromo DECIMAL(5,2) NULL DEFAULT NULL;