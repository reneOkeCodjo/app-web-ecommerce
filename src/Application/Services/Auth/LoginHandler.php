<?php

declare(strict_types=1);

namespace App\Application\Services\Auth;

use App\Application\DTO\LoginRequestDTO;
use App\Application\Security\LoginThrottle;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Handles login auth flow after presentation-layer validation.
 * Returns null when the caller should redirect to the products page.
 */
final class LoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SessionStoreInterface $session
    ) {
    }

    /**
     * @return string|null Feedback HTML, or null to redirect to products.
     */
    public function handle(string $method, ?LoginRequestDTO $request = null): ?string
    {
        return match (strtoupper($method)) {
            'POST' => $this->handlePost($request),
            'GET' => $this->handleGet(),
            default => throw new InvalidArgumentException('Invalid HTTP method.'),
        };
    }

    private function handleGet(): ?string
    {
        $auth = new Auth($this->userRepository, $this->session);

        if ($auth->isAuthenticated()) {
            return null;
        }

        return '';
    }

    private function handlePost(?LoginRequestDTO $request): ?string
    {
        if ($request === null) {
            throw new InvalidArgumentException('Login request is required for POST.');
        }

        // On crée le limiteur de tentatives pour cette session.
        $throttle = new LoginThrottle($this->session);

        // Si trop d'échecs ont déjà eu lieu, on refuse sans même vérifier le mot de passe.
        if ($throttle->isBlocked($request->getUsername())) {
            return $this->error('Too many attempts. Please try again later.');
        }

        // On protège l'appel au service d'authentification contre les erreurs imprévues.
        try {
            // On prépare le service d'authentification avec le dépôt d'utilisateurs et la session.
            $auth = new Auth($this->userRepository, $this->session);

            // On tente la connexion avec le nom d'utilisateur et le mot de passe reçus.
            $authenticated = $auth->login(
                $request->getUsername(),
                $request->getPassword()
            );

            // Si la connexion réussit...
            if ($authenticated) {
                // ...on efface les anciens échecs pour cet utilisateur.
                $throttle->clear($request->getUsername());

                // On renvoie null : la page sait qu'elle doit rediriger vers le catalogue.
                return null;
            }

            // Si la connexion échoue, on ajoute un échec au compteur.
            $throttle->recordFailure($request->getUsername());

            // On affiche un message volontairement vague (on ne dit pas si le login existe).
            return $this->error('Invalid username or password.');
        } catch (Throwable $e) {
            // En cas de problème technique, on affiche un message générique.
            return $this->error('An error occurred during login. Please try again later.');
        }
    }

    private function error(string $message): string
    {
        return '<p class="feedback feedback-error" role="alert">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p>';
    }
}
