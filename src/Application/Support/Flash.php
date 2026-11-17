<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce petit outil appartient à la couche « Application ».
namespace App\Application\Support;

// On a besoin du stockage de session.
use App\Infrastructure\Http\SessionStoreInterface;

/**
 * Messages « flash » : de petits messages affichés une seule fois.
 *
 * Exemple : après la création d'un produit, on redirige vers la liste.
 * Le message « Produit créé » est stocké en session, puis affiché
 * sur la page suivante, avant d'être effacé.
 */
final class Flash
{
    // Nom de la case de session qui contient les messages.
    private const KEY = '_flash_messages';

    // Types de messages autorisés.
    private const TYPES = ['success', 'error', 'info'];

    /**
     * Ajoute un message pour la prochaine page.
     */
    public static function add(
        SessionStoreInterface $session,
        string $type,
        string $message
    ): void {
        // On refuse un type inconnu : on le remplace par « info ».
        if (!in_array($type, self::TYPES, true)) {
            $type = 'info';
        }

        // On lit les messages déjà en attente.
        $messages = self::all($session);

        // On ajoute le nouveau message.
        $messages[] = ['type' => $type, 'message' => $message];

        // On enregistre la liste complète.
        $session->set(self::KEY, $messages);
    }

    /**
     * Récupère puis efface tous les messages en attente.
     *
     * @return list<array{type: string, message: string}>
     */
    public static function consume(SessionStoreInterface $session): array
    {
        // On lit les messages.
        $messages = self::all($session);

        // On les efface : ils ne seront pas affichés deux fois.
        $session->remove(self::KEY);

        return $messages;
    }

    /**
     * Lit les messages sans les effacer.
     *
     * @return list<array{type: string, message: string}>
     */
    private static function all(SessionStoreInterface $session): array
    {
        // On lit la valeur brute.
        $raw = $session->get(self::KEY);

        // Si ce n'est pas un tableau, il n'y a aucun message.
        if (!is_array($raw)) {
            return [];
        }

        // On garde seulement les entrées valides.
        $messages = [];

        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $type = $entry['type'] ?? 'info';
            $message = $entry['message'] ?? '';

            if (!is_string($type) || !is_string($message) || $message === '') {
                continue;
            }

            $messages[] = [
                'type' => in_array($type, self::TYPES, true) ? $type : 'info',
                'message' => $message,
            ];
        }

        return $messages;
    }
}
