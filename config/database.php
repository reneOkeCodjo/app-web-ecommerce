<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Cette classe appartient à l'espace de noms « Db_config ».
namespace Db_config;

// On importe l'exception standard de PHP.
use RuntimeException;

/**
 * Fabrique la connexion à la base de données MySQL.
 *
 * En cas d'échec, elle ne coupe plus la page avec « die() ».
 * Elle lève une exception que la page peut attraper pour afficher
 * un message propre, tout en gardant le détail technique dans les journaux.
 */
final class Database
{
    // On garde une seule configuration en mémoire pour toute la requête.
    private static ?DatabaseConfig $config = null;

    /**
     * Renvoie la configuration, en la créant la première fois.
     */
    public static function getConfig(): DatabaseConfig
    {
        if (self::$config === null) {
            self::$config = new DatabaseConfig();
        }

        return self::$config;
    }

    /**
     * Ouvre et renvoie une connexion MySQL prête à l'emploi.
     */
    public static function connect(): \mysqli
    {
        // On récupère les paramètres de connexion.
        $config = self::getConfig();

        // On demande à mysqli de lever des exceptions en cas d'erreur.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            // On crée la connexion avec l'hôte, l'utilisateur, le mot de passe, la base et le port.
            $connection = new \mysqli(
                $config->getHost(),
                $config->getUsername(),
                $config->getPassword(),
                $config->getDatabase(),
                $config->getPort()
            );

            // On force l'encodage UTF-8 complet (accents, symboles…).
            $connection->set_charset('utf8mb4');

            return $connection;
        } catch (\mysqli_sql_exception $e) {
            // On écrit le vrai message dans le journal du serveur.
            error_log('[database] ' . $e->getMessage());

            // On renvoie une exception neutre : le visiteur ne voit pas les détails.
            throw new RuntimeException('Database connection failed.', 0, $e);
        }
    }
}
