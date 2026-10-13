<?php

declare(strict_types=1);

namespace App\Application\Services\Auth;

use App\Application\DTO\RegisterRequestDTO;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Handles register auth flow after presentation-layer validation.
 * Returns null when the caller should redirect to the products page.
 */
final class RegisterHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SessionStoreInterface $session
    ) {
    }

    /**
     * @return string|null Feedback HTML, or null to redirect to products.
     */
    public function handle(string $method, ?RegisterRequestDTO $request = null): ?string
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

    private function handlePost(?RegisterRequestDTO $request): ?string
    {
        if ($request === null) {
            throw new InvalidArgumentException('Register request is required for POST.');
        }

        try {
            $auth = new Auth($this->userRepository, $this->session);
            $registered = $auth->register(
                $request->getUsername(),
                $request->getEmail(),
                $request->getPassword()
            );

            if ($registered) {
                return null;
            }

            return $this->error('Username or email is already taken.');
        } catch (Throwable $e) {
            return $this->error('An error occurred during registration. Please try again later.');
        }
    }

    private function error(string $message): string
    {
        return '<p class="feedback feedback-error" role="alert">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p>';
    }
}
