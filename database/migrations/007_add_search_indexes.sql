-- Migration : accélérer les recherches et les tris fréquents.
-- Date : 2026-11-17
-- Ordre : 007 (après la création des tables products et users).
--
-- Un index est comme l'index alphabétique d'un livre : MySQL trouve
-- les lignes plus vite sans lire toute la table.

-- Index sur la catégorie : utile pour filtrer le catalogue.
ALTER TABLE `product`
    ADD KEY `idx_product_type` (`product_type`);

-- Index sur la date d'entrée : utile pour trier les nouveautés.
ALTER TABLE `product`
    ADD KEY `idx_product_date_in` (`product_date_in`);

-- Index sur la promotion : utile pour compter les produits en promo.
ALTER TABLE `product`
    ADD KEY `idx_product_promo` (`ppromo`);

-- Index sur la date de connexion : utile pour les futurs écrans d'administration.
ALTER TABLE `users`
    ADD KEY `idx_user_date_login` (`user_date_login`);
