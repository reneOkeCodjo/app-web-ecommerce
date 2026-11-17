<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce service appartient à la couche « Application », dossier Auth.
namespace App\Application\Services\Auth;

// On a besoin du jeton CSRF et du stockage de session.
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\SessionStoreInterface;

// On a besoin du dépôt d'utilisateurs.
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;

/**
 * Service d'authentification : connexion, inscription et déconnexion.
 *
 * Il ne connaît ni HTML ni MySQL : il reçoit un dépôt et une session,
 * ce qui rend le code testable et facile à remplacer.
 */
final class Auth implements AuthInterface
{
    // Clé de session qui contient l'identifiant de l'utilisateur.
    private const SESSION_USER_ID = 'user_id';

    // Clé de session qui contient le type de compte (client, admin…).
    private const SESSION_COMPTE_TYPE = 'compte_type';

    // Type de compte qui donne accès à l'administration.
    private const COMPTE_TYPE_ADMIN = 'admin';

    /**
     * On reçoit les dépendances par le constructeur (injection).
     */
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SessionStoreInterface $session
    ) {
    }

    /**
     * Vérifie les identifiants et connecte l'utilisateur.
     */
    public function login(string $username, string $password): bool
    {
        // On cherche l'utilisateur par son nom de connexion.
        $user = $this->userRepository->findByUsername($username);

        // Utilisateur inconnu : on refuse sans dire pourquoi.
        if (!$user) {
            return false;
        }

        // Le mot de passe ne correspond pas : on refuse.
        if (!password_verify($password, $user->getPassword())) {
            return false;
        }

        // Tout est correct : on enregistre l'état de connexion.
        $this->persistAuth($user->getId(), $user->getCompteType());

        return true;
    }

    /**
     * Inscrit un nouvel utilisateur « client » et le connecte.
     */
    public function register(string $username, string $email, string $password): bool
    {
        // On refuse un nom déjà utilisé.
        if ($this->userRepository->findByUsername($username) !== null) {
            return false;
        }

        // On refuse une adresse e-mail déjà utilisée.
        if ($this->userRepository->findByEmail($email) !== null) {
            return false;
        }

        // On retrouve l'identifiant du type de compte « client ».
        $compteId = $this->userRepository->findCompteIdByType('client');

        // Si le type de compte n'existe pas, l'inscription est impossible.
        if ($compteId === null) {
            return false;
        }

        // On transforme le mot de passe en empreinte illisible (hash).
        // Argon2id est recommandé ; sinon on utilise l'algorithme par défaut de PHP.
        $passwordHash = password_hash(
            $password,
            defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT
        );

        // On crée l'utilisateur en base.
        $user = $this->userRepository->create($username, $email, $passwordHash, $compteId);

        // On connecte immédiatement le nouvel utilisateur.
        $this->persistAuth($user->getId(), $user->getCompteType());

        return true;
    }

    /**
     * Déconnecte l'utilisateur.
     */
    public function logout(): void
    {
        // On efface les informations d'authentification.
        $this->session->remove(self::SESSION_USER_ID);
        $this->session->remove(self::SESSION_COMPTE_TYPE);

        // On détruit complètement la session.
        $this->session->destroy();
    }

    /**
     * Indique si un utilisateur est connecté.
     */
    public function isAuthenticated(): bool
    {
        return $this->getAuthenticatedUserId() !== null;
    }

    /**
     * Indique si l'utilisateur connecté est administrateur.
     */
    public function isAdmin(): bool
    {
        return $this->isAuthenticated()
            && $this->getCompteType() === self::COMPTE_TYPE_ADMIN;
    }

    /**
     * Renvoie l'identifiant de l'utilisateur connecté, ou null.
     */
    public function getAuthenticatedUserId(): ?int
    {
        // On lit la valeur brute stockée en session.
        $raw = $this->session->get(self::SESSION_USER_ID);

        // Une valeur absente ou non numérique n'est pas valide.
        if ($raw === null || !is_numeric($raw)) {
            return null;
        }

        $userId = (int) $raw;

        // Un identifiant doit être strictement positif.
        return $userId > 0 ? $userId : null;
    }

    /**
     * Renvoie le type de compte de l'utilisateur connecté, ou null.
     */
    public function getCompteType(): ?string
    {
        $raw = $this->session->get(self::SESSION_COMPTE_TYPE);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return $raw;
    }

    /**
     * Enregistre l'état de connexion dans la session.
     */
    private function persistAuth(int $userId, string $compteType): void
    {
        // On change l'identifiant de session : un ancien cookie volé devient inutile.
        $this->session->regenerate();

        // On renouvelle aussi le jeton CSRF, car la session vient de changer.
        Csrf::rotate($this->session);

        // On mémorise l'utilisateur connecté.
        $this->session->set(self::SESSION_USER_ID, $userId);

        // On mémorise son type de compte.
        $this->session->set(self::SESSION_COMPTE_TYPE, $compteType);
    }
}
