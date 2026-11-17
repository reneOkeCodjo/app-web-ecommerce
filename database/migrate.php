<?php

// Ce script applique les fichiers SQL de migration un par un.
// Il ne s'exécute que depuis la ligne de commande (pas depuis un navigateur).
declare(strict_types=1);

// On refuse l'accès via le serveur web.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ce script doit être lancé en ligne de commande.');
}

// On charge l'autoload de Composer (classes du projet).
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Dépendances manquantes : lancez « composer install ».\n");
    exit(1);
}
require_once $autoload;

// On importe la classe de connexion à la base.
use Db_config\Database;

// On lit les options passées au script.
$options = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $options, true);
$statusOnly = in_array('--status', $options, true);

// On prépare le dossier des migrations.
$migrationsDirectory = __DIR__ . '/migrations';

// On vérifie que le dossier existe.
if (!is_dir($migrationsDirectory)) {
    fwrite(STDERR, "Dossier introuvable : database/migrations.\n");
    exit(1);
}

// On liste les fichiers .sql et on les trie par nom (001, 002, ...).
$files = glob($migrationsDirectory . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

// On ouvre la connexion à la base.
try {
    $connection = Database::connect();
} catch (\Throwable $e) {
    fwrite(STDERR, "Connexion à la base impossible. Vérifiez votre fichier .env.\n");
    exit(1);
}

// On crée la table qui mémorise les migrations déjà appliquées.
$connection->query(
    'CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `filename` VARCHAR(255) NOT NULL,
        `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

// On lit la liste des migrations déjà appliquées.
$appliedRows = $connection->query(
    'SELECT `filename` FROM `schema_migrations` ORDER BY `filename`'
)->fetch_all(MYSQLI_ASSOC);

// On transforme le résultat en tableau simple.
$applied = array_map(
    static fn (array $row): string => (string) $row['filename'],
    $appliedRows
);

/**
 * Exécute un fichier SQL pouvant contenir plusieurs instructions.
 *
 * @param \mysqli $connection Connexion active.
 * @param string $sql Contenu du fichier.
 */
function runSqlFile(\mysqli $connection, string $sql): void
{
    // Un fichier vide ne fait rien.
    if (trim($sql) === '') {
        return;
    }

    // On envoie toutes les instructions du fichier.
    $connection->multi_query($sql);

    // On vide les résultats éventuels pour libérer la connexion.
    do {
        $result = $connection->store_result();
        if ($result instanceof \mysqli_result) {
            $result->free();
        }
    } while ($connection->more_results() && $connection->next_result());
}

// On prépare la requête qui enregistre une migration.
$insertStatement = $connection->prepare(
    'INSERT INTO `schema_migrations` (`filename`) VALUES (?)'
);

// Compteurs pour le résumé final.
$appliedCount = 0;
$skippedCount = 0;
$pendingCount = 0;
$failed = false;

// On parcourt les fichiers dans l'ordre.
foreach ($files as $file) {
    // On garde uniquement le nom du fichier.
    $filename = basename($file);

    // Si la migration est déjà connue, on passe à la suivante.
    if (in_array($filename, $applied, true)) {
        $skippedCount++;
        continue;
    }

    // On compte les migrations en attente.
    $pendingCount++;

    // En mode « status », on affiche seulement la liste.
    if ($statusOnly) {
        echo "[en attente] {$filename}\n";
        continue;
    }

    // En mode simulation, on n'exécute rien.
    if ($dryRun) {
        echo "[simulation] {$filename}\n";
        continue;
    }

    // On lit le contenu du fichier.
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "[erreur] Lecture impossible : {$filename}\n");
        $failed = true;
        break;
    }

    // On exécute la migration.
    try {
        runSqlFile($connection, $sql);

        // On enregistre la migration comme appliquée.
        $insertStatement->bind_param('s', $filename);
        $insertStatement->execute();

        // On mémorise le nom pour éviter un doublon dans la même exécution.
        $applied[] = $filename;

        echo "[ok] {$filename}\n";
        $appliedCount++;
    } catch (\Throwable $e) {
        // On affiche un message lisible et on s'arrête.
        fwrite(STDERR, "[erreur] {$filename} : " . $e->getMessage() . "\n");
        $failed = true;
        break;
    }
}

// On ferme la requête préparée et la connexion.
$insertStatement->close();
$connection->close();

// On affiche un résumé.
if (!$statusOnly) {
    echo "\n";
    echo "Migrations appliquées : {$appliedCount}\n";
    echo "Migrations déjà à jour : {$skippedCount}\n";
    echo "Migrations en attente : {$pendingCount}\n";
}

// On termine avec un code d'erreur si une migration a échoué.
exit($failed ? 1 : 0);
