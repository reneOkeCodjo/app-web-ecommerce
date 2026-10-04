<?php
namespace App\Application\Services;

use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;

interface AuthInterface {
    /**
     * Authenticate a user with the given username and password.
     *
     * @param string $username The username of the user to authenticate.
     * @param string $password The password of the user to authenticate.
     * @return bool True if the user is authenticated, false otherwise.
     */
    public function login(string $username, string $password): bool;
}

class Auth implements AuthInterface {
    private static UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository) {
        self::$userRepository = $userRepository;;
    }

    public function login(string $username, string $password): bool {
        $user = self::$userRepository->findByUsername($username);

        if (!$user) {
            return false; // User not found
        }
        

        // Verify the password (assuming passwords are hashed)
        if (!password_verify($password, $user->getPassword())) {
            return false; // Invalid password
        }

        // Authentication successful
        return true;
    }
}