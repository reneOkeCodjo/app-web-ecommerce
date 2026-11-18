<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce fichier vit dans la couche technique qui parle à la base de données.
namespace App\Infrastructure\Persistence\DAO;

// On utilise le modèle User.
use App\Domain\Model\User;

/**
 * Contrat que doit respecter tout dépôt d'utilisateurs.
 */
interface UserRepositoryInterface
{
    /**
     * Cherche un utilisateur par son nom de connexion.
     */
    public function findByUsername(string $username): ?User;

    /**
     * Cherche un utilisateur par son adresse e-mail.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Crée un utilisateur et renvoie l'objet enregistré.
     */
    public function create(
        string $username,
        string $email,
        string $passwordHash,
        int $compteId
    ): User;

    /**
     * Renvoie l'identifiant du type de compte demandé (ex. « client »).
     */
    public function findCompteIdByType(string $type): ?int;

    /**
     * Cherche un utilisateur par son identifiant technique.
     */
    public function findById(int $id): ?User;

    /**
     * Renvoie tous les utilisateurs, du plus récent au plus ancien.
     *
     * @return array<int, User>
     */
    public function findAll(): array;

    /**
     * Met à jour le profil et le rôle d'un utilisateur.
     */
    public function updateProfile(int $id, string $username, string $email, int $compteId): bool;

    /**
     * Remplace l'empreinte du mot de passe.
     */
    public function updatePassword(int $id, string $passwordHash): bool;

    /**
     * Supprime définitivement un utilisateur.
     */
    public function delete(int $id): bool;

    /**
     * Enregistre l'horodatage de la dernière connexion réussie.
     */
    public function touchLastLogin(int $id): void;

    /**
     * Compte les utilisateurs qui possèdent un rôle donné.
     */
    public function countByType(string $type): int;
}
