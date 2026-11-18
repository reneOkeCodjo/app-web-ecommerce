<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce fichier vit dans la couche technique qui parle à la base de données.
namespace App\Infrastructure\Persistence\DAO;

// On utilise le modèle User.
use App\Domain\Model\User;
use RuntimeException;

/**
 * Dépôt d'utilisateurs stockés dans les tables MySQL `users` et `compte`.
 *
 * Toutes les requêtes sont préparées pour éviter les injections SQL.
 */
final class UserRepository implements UserRepositoryInterface
{
    // Connexion MySQL partagée avec le reste de la page.
    private \mysqli $connection;

    /**
     * On reçoit une connexion déjà ouverte.
     */
    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Cherche un utilisateur par son nom de connexion.
     */
    public function findByUsername(string $username): ?User
    {
        // On joint la table des comptes pour connaître le rôle (client, admin…).
        $statement = $this->connection->prepare(
            'SELECT u.user_id AS id,
                    u.user_login AS username,
                    u.user_mail AS email,
                    u.user_password AS password,
                    u.user_date_new AS date_new,
                    u.user_date_login AS date_login,
                    c.compte_type AS compte_type
             FROM users u
             INNER JOIN compte c ON c.compte_id = u.user_compte_id
             WHERE u.user_login = ?'
        );

        // On remplace le point d'interrogation par le nom reçu.
        $statement->bind_param('s', $username);
        $statement->execute();
        $result = $statement->get_result();

        // Aucun utilisateur trouvé.
        if ($result->num_rows === 0) {
            $statement->close();

            return null;
        }

        // On lit la ligne et on la transforme en objet.
        $row = $result->fetch_assoc();
        $statement->close();

        return $this->mapRow($row);
    }

    /**
     * Cherche un utilisateur par son adresse e-mail.
     */
    public function findByEmail(string $email): ?User
    {
        // Même principe que findByUsername, mais sur la colonne e-mail.
        $statement = $this->connection->prepare(
            'SELECT u.user_id AS id,
                    u.user_login AS username,
                    u.user_mail AS email,
                    u.user_password AS password,
                    u.user_date_new AS date_new,
                    u.user_date_login AS date_login,
                    c.compte_type AS compte_type
             FROM users u
             INNER JOIN compte c ON c.compte_id = u.user_compte_id
             WHERE u.user_mail = ?'
        );

        $statement->bind_param('s', $email);
        $statement->execute();
        $result = $statement->get_result();

        if ($result->num_rows === 0) {
            $statement->close();

            return null;
        }

        $row = $result->fetch_assoc();
        $statement->close();

        return $this->mapRow($row);
    }

    /**
     * Crée un utilisateur et renvoie l'objet enregistré.
     */
    public function create(
        string $username,
        string $email,
        string $passwordHash,
        int $compteId
    ): User {
        // On insère les quatre informations utiles.
        $statement = $this->connection->prepare(
            'INSERT INTO users (user_login, user_mail, user_password, user_compte_id)
             VALUES (?, ?, ?, ?)'
        );

        $statement->bind_param('sssi', $username, $email, $passwordHash, $compteId);

        // Si l'insertion échoue, on lève une exception.
        if (!$statement->execute()) {
            $statement->close();

            throw new RuntimeException('Impossible de créer l’utilisateur.');
        }

        // On récupère l'identifiant automatique créé par MySQL.
        $id = (int) $statement->insert_id;
        $statement->close();

        // On retrouve le type de compte pour construire un objet complet.
        $compteType = $this->findCompteTypeById($compteId) ?? 'client';

        return new User($id, $username, $email, $passwordHash, $compteType);
    }

    /**
     * Renvoie l'identifiant du type de compte demandé.
     */
    public function findCompteIdByType(string $type): ?int
    {
        // On cherche la première ligne correspondant au type.
        $statement = $this->connection->prepare(
            'SELECT compte_id FROM compte WHERE compte_type = ? LIMIT 1'
        );

        $statement->bind_param('s', $type);
        $statement->execute();
        $result = $statement->get_result();

        if ($result->num_rows === 0) {
            $statement->close();

            return null;
        }

        $row = $result->fetch_assoc();
        $statement->close();

        return (int) $row['compte_id'];
    }

    /**
     * Retrouve le libellé d'un type de compte à partir de son identifiant.
     */
    private function findCompteTypeById(int $compteId): ?string
    {
        $statement = $this->connection->prepare(
            'SELECT compte_type FROM compte WHERE compte_id = ? LIMIT 1'
        );

        $statement->bind_param('i', $compteId);
        $statement->execute();
        $result = $statement->get_result();

        if ($result->num_rows === 0) {
            $statement->close();

            return null;
        }

        $row = $result->fetch_assoc();
        $statement->close();

        return (string) $row['compte_type'];
    }

    /**
     * Cherche un utilisateur par son identifiant technique.
     */
    public function findById(int $id): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT u.user_id AS id,
                    u.user_login AS username,
                    u.user_mail AS email,
                    u.user_password AS password,
                    u.user_date_new AS date_new,
                    u.user_date_login AS date_login,
                    c.compte_type AS compte_type
             FROM users u
             INNER JOIN compte c ON c.compte_id = u.user_compte_id
             WHERE u.user_id = ?
             LIMIT 1'
        );

        $statement->bind_param('i', $id);
        $statement->execute();
        $result = $statement->get_result();

        if ($result->num_rows === 0) {
            $statement->close();

            return null;
        }

        $row = $result->fetch_assoc();
        $statement->close();

        return $this->mapRow($row);
    }

    /**
     * Renvoie tous les utilisateurs, du plus récent au plus ancien.
     *
     * @return array<int, User>
     */
    public function findAll(): array
    {
        $result = $this->connection->query(
            'SELECT u.user_id AS id,
                    u.user_login AS username,
                    u.user_mail AS email,
                    u.user_password AS password,
                    u.user_date_new AS date_new,
                    u.user_date_login AS date_login,
                    c.compte_type AS compte_type
             FROM users u
             INNER JOIN compte c ON c.compte_id = u.user_compte_id
             ORDER BY u.user_date_new DESC, u.user_id DESC'
        );

        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = $this->mapRow($row);
        }

        return $users;
    }

    /**
     * Met à jour le profil et le rôle d'un utilisateur.
     */
    public function updateProfile(int $id, string $username, string $email, int $compteId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE users
             SET user_login = ?, user_mail = ?, user_compte_id = ?
             WHERE user_id = ?'
        );

        $statement->bind_param('ssii', $username, $email, $compteId, $id);
        $success = $statement->execute();
        $statement->close();

        return $success;
    }

    /**
     * Remplace l'empreinte du mot de passe.
     */
    public function updatePassword(int $id, string $passwordHash): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET user_password = ? WHERE user_id = ?'
        );

        $statement->bind_param('si', $passwordHash, $id);
        $success = $statement->execute();
        $statement->close();

        return $success;
    }

    /**
     * Supprime définitivement un utilisateur.
     */
    public function delete(int $id): bool
    {
        $statement = $this->connection->prepare('DELETE FROM users WHERE user_id = ?');
        $statement->bind_param('i', $id);
        $success = $statement->execute();
        $statement->close();

        return $success;
    }

    /**
     * Enregistre l'horodatage de la dernière connexion réussie.
     */
    public function touchLastLogin(int $id): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET user_date_login = NOW() WHERE user_id = ?'
        );

        $statement->bind_param('i', $id);
        $statement->execute();
        $statement->close();
    }

    /**
     * Compte les utilisateurs qui possèdent un rôle donné.
     */
    public function countByType(string $type): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) AS total
             FROM users u
             INNER JOIN compte c ON c.compte_id = u.user_compte_id
             WHERE c.compte_type = ?'
        );

        $statement->bind_param('s', $type);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result->fetch_assoc();
        $statement->close();

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Transforme une ligne SQL en objet User.
     *
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['password'],
            (string) $row['compte_type'],
            isset($row['date_new']) ? (string) $row['date_new'] : null,
            isset($row['date_login']) ? (string) $row['date_login'] : null
        );
    }
}
