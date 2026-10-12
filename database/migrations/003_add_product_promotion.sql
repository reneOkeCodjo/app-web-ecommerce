-- Ajout d'un pourcentage de promotion facultatif.
ALTER TABLE product
ADD COLUMN ppromo DECIMAL(5,2) NULL DEFAULT NULL;