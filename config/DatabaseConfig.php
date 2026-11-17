<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Cette classe appartient à l'espace de noms « Db_config ».
namespace Db_config;

/**
 * Lit et conserve les paramètres de connexion MySQL.
 *
 * Les valeurs viennent du fichier .env. Si une valeur manque,
 * on utilise une valeur par défaut adaptée au développement local.
 */
final class DatabaseConfig
{
    // Adresse du serveur MySQL.
    private string $host;

    // Nom d'utilisateur MySQL.
    private string $username;

    // Mot de passe MySQL.
    private string $password;

    // Nom de la base de données.
    private string $database;

    // Port réseau de MySQL.
    private int $port;

    /**
     * Au moment de la création, on charge l'environnement puis les valeurs.
     */
    public function __construct()
    {
        // On charge le fichier .env si nécessaire.
        $this->loadEnvironment();

        // On lit chaque valeur, avec une valeur par défaut si elle est absente.
        $this->host = (string) ($_ENV['DB_HOST'] ?? 'localhost');
        $this->port = (int) ($_ENV['DB_PORT'] ?? 3306);
        $this->database = (string) ($_ENV['DB_NAME'] ?? 'my_database');
        $this->username = (string) ($_ENV['DB_USER'] ?? 'root');
        $this->password = (string) ($_ENV['DB_PASSWORD'] ?? '');
    }

    /**
     * Renvoie l'adresse du serveur.
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Renvoie le nom d'utilisateur MySQL.
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Renvoie le mot de passe MySQL.
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Renvoie le nom de la base de données.
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * Renvoie le port MySQL.
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Charge le fichier .env une seule fois, si les variables ne sont pas déjà là.
     */
    private function loadEnvironment(): void
    {
        // Si DB_HOST est déjà défini, le fichier .env a déjà été chargé.
        if (isset($_ENV['DB_HOST'])) {
            return;
        }

        // On construit le chemin du fichier .env à la racine du projet.
        $envFile = dirname(__DIR__) . '/.env';

        // Sans fichier .env ni bibliothèque Dotenv, on garde les valeurs par défaut.
        if (!is_file($envFile) || !class_exists(\Dotenv\Dotenv::class)) {
            return;
        }

        // On charge le fichier .env sans erreur s'il est incomplet.
        \Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    }
}
