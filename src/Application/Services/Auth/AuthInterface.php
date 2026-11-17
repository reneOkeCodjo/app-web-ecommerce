<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce fichier décrit le contrat du service d'authentification.
namespace App\Application\Services\Auth;

/**
 * Contrat du service qui gère la connexion et l'inscription.
 */
interface AuthInterface
{
    /**
     * Authentifie un utilisateur et mémorise son état dans la session.
     */
    public function login(string $username, string $password): bool;

    /**
     * Inscrit un nouvel utilisateur et le connecte immédiatement.
     */
    public function register(string $username, string $email, string $password): bool;

    /**
     * Déconnecte l'utilisateur et détruit sa session.
     */
    public function logout(): void;

    /**
     * Indique si un utilisateur est connecté.
     */
    public function isAuthenticated(): bool;

    /**
     * Indique si l'utilisateur connecté est administrateur.
     */
    public function isAdmin(): bool;

    /**
     * Renvoie l'identifiant de l'utilisateur connecté, ou null.
     */
    public function getAuthenticatedUserId(): ?int;

    /**
     * Renvoie le type de compte de l'utilisateur connecté, ou null.
     */
    public function getCompteType(): ?string;
}
