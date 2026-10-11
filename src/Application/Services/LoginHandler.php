<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use Throwable;

/**
 * Handles login page request flow and returns feedback HTML.
 */
final class LoginHandler
{
    public function __construct(
        private ValidationInterface $validation,
        private UserRepositoryInterface $userRepository,
        private SessionStoreInterface $session
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public function handle(string $method, array $post): string
    {
        if (strtoupper($method) !== 'POST') {
            return $this->handleGet();
        }

        return $this->handlePost($post);
    }

    private function handleGet(): string
    {
        $auth = new Auth($this->userRepository, $this->session);

        if ($auth->isAuthenticated()) {
            return $this->success('You are already signed in.');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $post
     */
    private function handlePost(array $post): string
    {
        $validationResult = $this->validation->validateLogin(
            $post['username'] ?? null,
            $post['password'] ?? null
        );

        if (!$validationResult->isValid()) {
            return $this->error((string) $validationResult->getFirstError());
        }

        $loginDto = $validationResult->getLoginRequest();

        try {
            $auth = new Auth($this->userRepository, $this->session);
            $authenticated = $auth->login(
                $loginDto->getUsername(),
                $loginDto->getPassword()
            );

            if ($authenticated) {
                return $this->success('Login successful!');
            }

            return $this->error('Invalid username or password.');
        } catch (Throwable $e) {
            return $this->error('An error occurred during login. Please try again later.');
        }
    }

    private function success(string $message): string
    {
        return '<p class="feedback feedback-success" role="status">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p>';
    }

    private function error(string $message): string
    {
        return '<p class="feedback feedback-error" role="alert">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p>';
    }
}
