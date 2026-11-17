<?php

// On impose des types stricts pour éviter les erreurs silencieuses.
declare(strict_types=1);

// Ce fichier fait partie de la couche "Application" et du dossier "Security".
namespace App\Application\Security;

// On a besoin du contrat de session pour ranger les compteurs.
use App\Infrastructure\Http\SessionStoreInterface;

/**
 * Cette classe limite le nombre de tentatives de connexion.
 *
 * Principe simple : après 5 mauvais mots de passe pour un même utilisateur
 * et une même adresse IP, on bloque pendant 15 minutes.
 * Cela ralentit les attaques qui essaient des milliers de mots de passe.
 *
 * Les compteurs sont stockés dans la session : aucune base de données n'est
 * nécessaire pour ce petit projet.
 */
final class LoginThrottle
{
    // Nom de la case de session qui contient tous les compteurs.
    private const KEY = '_login_throttle';

    /**
     * @param SessionStoreInterface $session       Stockage des compteurs (la session PHP).
     * @param int                   $maxAttempts   Nombre d'échecs autorisés avant blocage.
     * @param int                   $windowSeconds Durée du blocage et de la fenêtre d'observation.
     */
    public function __construct(
        private SessionStoreInterface $session,
        private int $maxAttempts = 5,
        private int $windowSeconds = 900
    ) {
    }

    /**
     * Indique si l'utilisateur est actuellement bloqué.
     */
    public function isBlocked(string $username): bool
    {
        // S'il reste du temps de blocage, la réponse est "oui".
        return $this->remainingSeconds($username) > 0;
    }

    /**
     * Enregistre un nouvel échec de connexion.
     */
    public function recordFailure(string $username): void
    {
        // On récupère tous les compteurs existants.
        $attempts = $this->attempts();

        // On calcule une clé unique à partir du nom d'utilisateur et de l'adresse IP.
        $key = $this->key($username);

        // On note l'heure actuelle (en secondes depuis 1970).
        $now = time();

        // Si aucun compteur n'existe pour cette clé, on part de zéro à l'instant présent.
        $entry = $attempts[$key] ?? ['count' => 0, 'first' => $now];

        // Si la fenêtre de temps est dépassée, on remet le compteur à zéro.
        if (($now - (int) $entry['first']) > $this->windowSeconds) {
            $entry = ['count' => 0, 'first' => $now];
        }

        // On ajoute 1 au nombre d'échecs.
        $entry['count'] = (int) $entry['count'] + 1;

        // On enregistre l'entrée mise à jour.
        $attempts[$key] = $entry;

        // On nettoie les anciennes entrées puis on sauvegarde dans la session.
        $this->session->set(self::KEY, $this->prune($attempts, $now));
    }

    /**
     * Efface les échecs d'un utilisateur (après une connexion réussie).
     */
    public function clear(string $username): void
    {
        // On lit les compteurs.
        $attempts = $this->attempts();

        // On supprime uniquement l'entrée de cet utilisateur.
        unset($attempts[$this->key($username)]);

        // On sauvegarde la liste nettoyée.
        $this->session->set(self::KEY, $attempts);
    }

    /**
     * Renvoie le nombre de secondes restantes avant la fin du blocage.
     * Zéro signifie "pas bloqué".
     */
    public function remainingSeconds(string $username): int
    {
        // On lit les compteurs.
        $attempts = $this->attempts();

        // On cherche l'entrée de cet utilisateur.
        $entry = $attempts[$this->key($username)] ?? null;

        // Si l'entrée n'existe pas ou n'a pas atteint la limite, aucun blocage.
        if ($entry === null || (int) $entry['count'] < $this->maxAttempts) {
            return 0;
        }

        // On calcule le temps déjà écoulé depuis le premier échec.
        $elapsed = time() - (int) $entry['first'];

        // On renvoie le temps restant, sans jamais descendre sous zéro.
        return max(0, $this->windowSeconds - $elapsed);
    }

    /**
     * Lit et renvoie tous les compteurs depuis la session.
     *
     * @return array<string, array{count: int, first: int}>
     */
    private function attempts(): array
    {
        // On lit la valeur brute stockée dans la session.
        $raw = $this->session->get(self::KEY);

        // Si ce n'est pas un tableau, on renvoie un tableau vide.
        return is_array($raw) ? $raw : [];
    }

    /**
     * Supprime les entrées trop anciennes pour garder la session légère.
     *
     * @param array<string, array{count: int, first: int}> $attempts
     * @return array<string, array{count: int, first: int}>
     */
    private function prune(array $attempts, int $now): array
    {
        // On parcourt chaque compteur.
        foreach ($attempts as $key => $entry) {
            // Si l'entrée est plus vieille que la fenêtre autorisée...
            if (($now - (int) $entry['first']) > $this->windowSeconds) {
                // ...on la supprime.
                unset($attempts[$key]);
            }
        }

        // On renvoie la liste nettoyée.
        return $attempts;
    }

    /**
     * Construit une clé anonyme et unique pour un utilisateur + une IP.
     */
    private function key(string $username): string
    {
        // On récupère l'adresse IP du visiteur (ou "unknown" si elle est absente).
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // On met le nom en minuscules, on enlève les espaces inutiles,
        // puis on hache le tout pour ne pas stocker le nom en clair.
        return hash('sha256', mb_strtolower(trim($username)) . '|' . $ip);
    }
}
