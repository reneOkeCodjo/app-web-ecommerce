<?php

// On demande à PHP d'être strict sur les types (évite les conversions silencieuses).
declare(strict_types=1);

// Ce fichier appartient à l'espace de noms "App\Infrastructure\Http" (couche technique HTTP).
namespace App\Infrastructure\Http;

/**
 * Cette classe fabrique et vérifie un jeton de sécurité appelé "CSRF".
 *
 * Un jeton CSRF est un code secret aléatoire stocké dans la session.
 * Il est ajouté dans chaque formulaire, puis vérifié à l'envoi.
 * But : empêcher un site malveillant de faire envoyer un formulaire à la place
 * de l'utilisateur connecté (falsification de requête).
 */
final class Csrf
{
    // Nom de la case dans la session où l'on range le jeton.
    private const KEY = '_csrf_token';

    /**
     * Renvoie le jeton de la session. S'il n'existe pas encore, on le crée.
     */
    public static function token(SessionStoreInterface $session): string
    {
        // On lit le jeton déjà enregistré dans la session (ou null s'il n'y en a pas).
        $token = $session->get(self::KEY);

        // On vérifie que la valeur est bien un texte et qu'elle est assez longue.
        if (!is_string($token) || strlen($token) < 32) {
            // On fabrique 32 octets aléatoires, puis on les transforme en texte lisible en hexadécimal.
            $token = bin2hex(random_bytes(32));

            // On enregistre ce nouveau jeton dans la session pour les prochains formulaires.
            $session->set(self::KEY, $token);
        }

        // On renvoie le jeton (ancien ou nouveau).
        return $token;
    }

    /**
     * Renvoie le morceau de HTML à insérer dans un formulaire.
     * C'est un champ invisible qui transporte le jeton.
     */
    public static function field(SessionStoreInterface $session): string
    {
        // On construit un <input> caché nommé "_csrf" avec le jeton, en échappant le texte pour le HTML.
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::token($session), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    /**
     * Vérifie qu'un jeton reçu correspond bien à celui de la session.
     */
    public static function validate(SessionStoreInterface $session, mixed $token): bool
    {
        // On récupère le jeton officiel enregistré côté serveur.
        $stored = $session->get(self::KEY);

        // Les trois conditions doivent être vraies : jeton serveur texte, jeton reçu texte non vide,
        // et comparaison à temps constant (hash_equals) pour éviter certaines attaques par mesure du temps.
        return is_string($stored)
            && is_string($token)
            && $token !== ''
            && hash_equals($stored, $token);
    }

    /**
     * Remplace le jeton par un nouveau (utilisé après une connexion réussie).
     */
    public static function rotate(SessionStoreInterface $session): void
    {
        // On génère un jeton tout neuf et on l'enregistre dans la session.
        $session->set(self::KEY, bin2hex(random_bytes(32)));
    }
}
