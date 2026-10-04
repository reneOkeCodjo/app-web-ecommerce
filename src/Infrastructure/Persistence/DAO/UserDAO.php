<?php
namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\User;

interface UserRepositoryInterface
{
    /**
     * Find a user by their username.
     *
     * @param string $username The username of the user to find.
     * @return User|null The user if found, or null if not found.
     */
    public function findByUsername(string $username): ?User;
}

/**
 * Class UserRepository
 * Implements the UserRepositoryInterface to interact with the database.
 * Interacts with the 'users' table in the database.
 */
class UserRepository implements UserRepositoryInterface
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->connection->prepare('SELECT * FROM user WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return null;
        }

        $row = $result->fetch_assoc();
        return new User($row['id'], $row['username'], $row['email'], $row['password']);
    }
}
