<?php

declare(strict_types=1);

namespace App\Application\Services\Auth;

use App\Application\DTO\LoginRequestDTO;
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

        try {
            $auth = new Auth($this->userRepository, $this->session);
            $authenticated = $auth->login(
                $request->getUsername(),
                $request->getPassword()
            );

            if (!$authenticated) {
                return $this->error('Invalid username or password.');
            }

            if (!$auth->isAdmin()) {
                $auth->logout();

                return $this->error('Admin access required.');
            }

            return null;
        } catch (Throwable $e) {
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
