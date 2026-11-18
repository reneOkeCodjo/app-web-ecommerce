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
 * Handles admin login after presentation-layer validation.
 * Returns null when the caller should redirect to the admin dashboard.
 */
final class AdminLoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SessionStoreInterface $session
    ) {
    }

    /**
     * @return string|null Feedback HTML, or null to redirect to the admin dashboard.
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

        if ($auth->isAdmin()) {
            return null;
        }

        return '';
    }

    private function handlePost(?LoginRequestDTO $request): ?string
    {
        if ($request === null) {
            throw new InvalidArgumentException('Login request is required for POST.');
        }

        // On prépare le service d'authentification.
        $auth = new Auth($this->userRepository, $this->session);

        // Un administrateur déjà connecté repart vers le tableau de bord.
        if ($auth->isAdminFresh()) {
            return null;
        }

        // Toute autre session ouverte est refusée pour empêcher le changement de compte.
        if ($auth->isAuthenticated()) {
            return $this->error('Admin access required.');
        }

        // On crée le limiteur de tentatives pour cette session.
        $throttle = new LoginThrottle($this->session);

        // Si l'utilisateur est déjà bloqué, on refuse directement.
        if ($throttle->isBlocked($request->getUsername())) {
            return $this->error('Too many attempts. Please try again later.');
        }

        // On protège le processus contre les erreurs imprévues.
        try {
            // On tente la connexion en exigeant explicitement le rôle administrateur.
            $authenticated = $auth->login(
                $request->getUsername(),
                $request->getPassword(),
                'admin'
            );

            // Si les identifiants sont faux ou si le rôle ne convient pas...
            if (!$authenticated) {
                // ...on compte un échec.
                $throttle->recordFailure($request->getUsername());

                // On distingue un vrai compte non-admin d'un mot de passe incorrect.
                $candidate = $this->userRepository->findByUsername($request->getUsername());

                if (
                    $candidate !== null
                    && !$candidate->isAdmin()
                    && password_verify($request->getPassword(), $candidate->getPassword())
                ) {
                    return $this->error('Admin access required.');
                }

                // ...et on affiche un message générique.
                return $this->error('Invalid username or password.');
            }

            // Si tout est correct, on remet le compteur d'échecs à zéro.
            $throttle->clear($request->getUsername());

            // On renvoie null : la page redirigera vers le tableau de bord admin.
            return null;
        } catch (Throwable $e) {
            // En cas de problème technique, message générique.
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
