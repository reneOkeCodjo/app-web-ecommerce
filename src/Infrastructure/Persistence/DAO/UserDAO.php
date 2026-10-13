<?php
namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\User;
use RuntimeException;

interface UserRepositoryInterface
{
    /**
     * Find a user by their username.
     *
     * @param string $username The username of the user to find.
     * @return User|null The user if found, or null if not found.
     */
    public function findByUsername(string $username): ?User;

    /**
     * Find a user by their email.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create a user and return the persisted entity.
     */
    public function create(string $username, string $email, string $passwordHash, int $compteId): User;

    /**
     * Resolve a compte id by type (e.g. client).
     */
    public function findCompteIdByType(string $type): ?int;
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
        $stmt = $this->connection->prepare(
            'SELECT u.user_id AS id,
                    u.user_login AS username,
                    u.user_mail AS email,
                    u.user_password AS password,
                    c.compte_type AS compte_type
             FROM users u
             INNER JOIN compte c ON c.compte_id = u.user_compte_id
             WHERE u.user_login = ?'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        return $this->mapRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT u.user_id AS id,
                    u.user_login AS username,
                    u.user_mail AS email,
                    u.user_password AS password,
                    c.compte_type AS compte_type
             FROM users u
             INNER JOIN compte c ON c.compte_id = u.user_compte_id
             WHERE u.user_mail = ?'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        return $this->mapRow($row);
    }

    public function create(string $username, string $email, string $passwordHash, int $compteId): User
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO users (user_login, user_mail, user_password, user_compte_id)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('sssi', $username, $email, $passwordHash, $compteId);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to create user.');
        }

        $id = (int) $stmt->insert_id;
        $stmt->close();

        $compteType = $this->findCompteTypeById($compteId) ?? 'client';

        return new User($id, $username, $email, $passwordHash, $compteType);
    }

    public function findCompteIdByType(string $type): ?int
    {
        $stmt = $this->connection->prepare(
            'SELECT compte_id FROM compte WHERE compte_type = ? LIMIT 1'
        );
        $stmt->bind_param('s', $type);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        return (int) $row['compte_id'];
    }

    private function findCompteTypeById(int $compteId): ?string
    {
        $stmt = $this->connection->prepare(
            'SELECT compte_type FROM compte WHERE compte_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $compteId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        return (string) $row['compte_type'];
    }

    /**
     * @param array{id: int|string, username: string, email: string, password: string, compte_type: string} $row
     */
    private function mapRow(array $row): User
    {
        return new User(
            (int) $row['id'],
            $row['username'],
            $row['email'],
            $row['password'],
            $row['compte_type']
        );
    }
}
