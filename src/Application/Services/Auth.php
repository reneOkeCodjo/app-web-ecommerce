<?php

namespace App\Application\Services;

use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;

interface AuthInterface
{
    /**
     * Authenticate a user and persist auth state in the PHP session.
     *
     * @return bool True if the user is authenticated, false otherwise.
     */
    public function login(string $username, string $password): bool;

    /**
     * Register a new user and persist auth state in the PHP session.
     *
     * @return bool True if registration succeeded, false otherwise.
     */
    public function register(string $username, string $email, string $password): bool;

    /**
     * Clear auth data and destroy the session.
     */
    public function logout(): void;

    /**
     * Whether a valid user id is stored in the session.
     */
    public function isAuthenticated(): bool;

    /**
     * User id from the session, or null if missing/invalid.
     */
    public function getAuthenticatedUserId(): ?int;
}

class Auth implements AuthInterface
{
    private const SESSION_USER_ID = 'user_id';

    private UserRepositoryInterface $userRepository;
    private SessionStoreInterface $session;

    public function __construct(
        UserRepositoryInterface $userRepository,
        SessionStoreInterface $session
    ) {
        $this->userRepository = $userRepository;
        $this->session = $session;
    }

    public function login(string $username, string $password): bool
    {
        $user = $this->userRepository->findByUsername($username);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user->getPassword())) {
            return false;
        }

        $this->session->regenerate();
        $this->session->set(self::SESSION_USER_ID, $user->getId());

        return true;
    }

    /**
     * Register a new client user and persist auth state in the session.
     *
     * @return bool True when registration succeeded, false when username/email already exists
     *              or the client compte type is missing.
     */
    public function register(string $username, string $email, string $password): bool
    {
        if ($this->userRepository->findByUsername($username) !== null) {
            return false;
        }

        if ($this->userRepository->findByEmail($email) !== null) {
            return false;
        }

        $compteId = $this->userRepository->findCompteIdByType('client');
        if ($compteId === null) {
            return false;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $user = $this->userRepository->create($username, $email, $passwordHash, $compteId);

        $this->session->regenerate();
        $this->session->set(self::SESSION_USER_ID, $user->getId());

        return true;
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_USER_ID);
        $this->session->destroy();
    }

    public function isAuthenticated(): bool
    {
        return $this->getAuthenticatedUserId() !== null;
    }

    public function getAuthenticatedUserId(): ?int
    {
        $raw = $this->session->get(self::SESSION_USER_ID);

        if ($raw === null || !is_numeric($raw)) {
            return null;
        }

        $userId = (int) $raw;

        return $userId > 0 ? $userId : null;
    }
}
