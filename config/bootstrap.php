<?php

// On active les types stricts de PHP.
declare(strict_types=1);

/**
 * Fichier de démarrage commun à toutes les pages publiques.
 *
 * Il fait toujours la même chose, dans le même ordre :
 * 1. charger les classes PHP (autoload) ;
 * 2. charger les variables du fichier .env ;
 * 3. régler les erreurs et le fuseau horaire ;
 * 4. envoyer des en-têtes HTTP de sécurité au navigateur.
 *
 * Ainsi, chaque page n'a qu'une seule ligne à retenir :
 * require_once __DIR__ . '/../config/bootstrap.php';
 */

// On charge l'autoload de Composer : il trouve automatiquement les classes du projet.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Ce repère indique aux fichiers partiels que le démarrage a bien eu lieu.
define('APP_BOOTSTRAPPED', true);

// On importe les classes utilisées plus bas.
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\SessionStoreInterface;
use Dotenv\Dotenv;

// Si la fonction "env" n'existe pas encore, on la crée.
if (!function_exists('env')) {
    /**
     * Lit une variable d'environnement, avec une valeur par défaut si elle est absente.
     */
    function env(string $key, mixed $default = null): mixed
    {
        // On cherche la variable dans $_ENV, puis $_SERVER, puis dans l'environnement système.
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        // Si la valeur est absente ou vide, on renvoie la valeur par défaut.
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        // Sinon, on renvoie la valeur trouvée.
        return $value;
    }
}

// Si la fonction "app_env" n'existe pas encore, on la crée.
if (!function_exists('app_env')) {
    /**
     * Indique l'environnement courant : "local", "test" ou "production".
     */
    function app_env(): string
    {
        // On lit APP_ENV, ou "local" par défaut.
        return (string) env('APP_ENV', 'local');
    }
}

// Si la fonction "is_https" n'existe pas encore, on la crée.
if (!function_exists('is_https')) {
    /**
     * Dit si la page est servie en HTTPS (connexion chiffrée).
     */
    function is_https(): bool
    {
        // On regarde plusieurs indices : la variable HTTPS, le port 443,
        // ou l'en-tête envoyé par un proxy (X-Forwarded-Proto).
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}

// Si la fonction "csp_nonce" n'existe pas encore, on la crée.
if (!function_exists('csp_nonce')) {
    /**
     * Renvoie un code aléatoire "nonce" utilisé par la politique de sécurité (CSP).
     * Seuls les scripts portant ce code sont autorisés à s'exécuter.
     */
    function csp_nonce(): string
    {
        // Si le code n'existe pas encore pour cette requête, on le génère.
        if (!isset($GLOBALS['__csp_nonce'])) {
            $GLOBALS['__csp_nonce'] = base64_encode(random_bytes(18));
        }

        // On renvoie le code (toujours le même pendant une requête).
        return (string) $GLOBALS['__csp_nonce'];
    }
}

// Si la fonction "csrf_field" n'existe pas encore, on la crée.
if (!function_exists('csrf_field')) {
    /**
     * Raccourci pour insérer le champ caché CSRF dans un formulaire.
     */
    function csrf_field(SessionStoreInterface $session): string
    {
        // On délègue la fabrication du champ à la classe Csrf.
        return Csrf::field($session);
    }
}

// Si la fonction "csrf_verify" n'existe pas encore, on la crée.
if (!function_exists('csrf_verify')) {
    /**
     * Raccourci pour vérifier le jeton CSRF envoyé par un formulaire.
     */
    function csrf_verify(SessionStoreInterface $session, mixed $token = null): bool
    {
        // Si aucun jeton n'est fourni, on prend celui du formulaire ($_POST).
        $token ??= $_POST['_csrf'] ?? null;

        // On délègue la comparaison à la classe Csrf.
        return Csrf::validate($session, $token);
    }
}

// Si la fonction "e" n'existe pas encore, on la crée.
if (!function_exists('e')) {
    /**
     * Échappe un texte avant de l'afficher dans du HTML.
     *
     * Cela empêche un visiteur malveillant d'injecter du code dans la page.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

// Si la fonction "asset_url" n'existe pas encore, on la crée.
if (!function_exists('asset_url')) {
    /**
     * Construit l'adresse d'un fichier CSS ou JS, avec un numéro de version.
     *
     * Le numéro de version change quand le fichier change : le navigateur
     * recharge alors automatiquement la nouvelle version au lieu du cache.
     */
    function asset_url(string $path, string $prefix = ''): string
    {
        // On construit le chemin réel du fichier dans le dossier public.
        $absolute = dirname(__DIR__) . '/public/' . ltrim($path, '/');

        // On lit sa date de modification si le fichier existe.
        $mtime = is_file($absolute) ? filemtime($absolute) : false;

        // On renvoie l'adresse, avec ?v=... si la date est connue.
        return $prefix . ltrim($path, '/') . ($mtime !== false ? '?v=' . $mtime : '');
    }
}

// On charge le fichier .env s'il existe, sans faire d'erreur s'il est absent.
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// On prépare le dossier où seront écrits les journaux d'erreurs.
$logDir = dirname(__DIR__) . '/storage/logs';

// Si le dossier n'existe pas, on le crée (le @ évite un message si deux requêtes le créent en même temps).
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

// On retient si l'application tourne en production.
$isProduction = app_env() === 'production';

// On demande à PHP de signaler toutes les erreurs.
error_reporting(E_ALL);

// En production, on cache les erreurs au visiteur ; en local, on les affiche pour développer.
ini_set('display_errors', $isProduction ? '0' : '1');

// On active l'écriture des erreurs dans un fichier.
ini_set('log_errors', '1');

// On indique le fichier de journal des erreurs PHP.
ini_set('error_log', $logDir . '/php-error.log');

// On règle le fuseau horaire (utile pour les dates et heures).
date_default_timezone_set((string) env('APP_TIMEZONE', 'Europe/Paris'));

// Les en-têtes HTTP n'ont de sens que pour une page web, pas pour un script en ligne de commande.
if (PHP_SAPI !== 'cli') {
    // Empêche le navigateur de deviner le type d'un fichier (protection contre certaines attaques).
    header('X-Content-Type-Options: nosniff');

    // Empêche un autre site d'afficher cette page dans un cadre (clickjacking).
    header('X-Frame-Options: DENY');

    // Limite les informations envoyées quand on quitte le site.
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Interdit l'accès à la caméra, au micro, à la géolocalisation et au paiement.
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    // Isole la fenêtre des autres origines (protection supplémentaire).
    header('Cross-Origin-Opener-Policy: same-origin');

    // Empêche d'inclure nos ressources depuis un autre site.
    header('Cross-Origin-Resource-Policy: same-origin');

    // Si la connexion est chiffrée, on demande au navigateur de toujours utiliser HTTPS pendant un an.
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // On récupère le code aléatoire de cette requête.
    $nonce = csp_nonce();

    // On construit la politique de sécurité du contenu (CSP).
    // Chaque ligne indique d'où peuvent venir les ressources.
    $csp = implode('; ', [
        "default-src 'self'",                               // Par défaut : uniquement notre site.
        "base-uri 'self'",                                  // La balise <base> ne peut pointer que vers notre site.
        "object-src 'none'",                                // Interdit les plugins (Flash, Java, etc.).
        "frame-ancestors 'none'",                           // Personne ne peut nous afficher dans une iframe.
        "form-action 'self'",                               // Les formulaires ne peuvent envoyer que vers notre site.
        "img-src 'self' data: blob:",                       // Images : notre site, données intégrées et aperçus locaux.
        "font-src 'self' https://fonts.gstatic.com data:",  // Polices : notre site et Google Fonts.
        "style-src 'self' https://fonts.googleapis.com",     // Feuilles de style : notre site et Google Fonts.
        "script-src 'self' 'nonce-" . $nonce . "'",          // Scripts : notre site et scripts marqués du nonce.
        "connect-src 'self'",                                // Requêtes réseau : uniquement notre site.
        "manifest-src 'self'",                               // Manifeste d'application : notre site.
    ]);

    // On envoie la politique au navigateur.
    header('Content-Security-Policy: ' . $csp);
}
