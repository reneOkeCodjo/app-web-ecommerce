<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\UserRepositoryInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Handles register page request flow and returns feedback HTML.
 */
final class RegisterHandler
{
    public function __construct(
        private ValidationInterface $validation,
        private UserRepositoryInterface $userRepository,
        private SessionStoreInterface $session
    ) {
    }

    /**
     * Handle the request.
     * @param string $method The HTTP method.
     * @param array<string, mixed> $post The POST data.
     * @return string The feedback HTML.
     */
    public function handle(string $method, array $post): string
    {
        return match (strtoupper($method)) {
            'POST' => $this->handlePost($post),
            'GET' => $this->handleGet(),
            default => throw new InvalidArgumentException('Invalid HTTP method.'),
        };
    }

    /**
     * Handle the GET request.
     * Check if the user is already authenticated.
     * @return string The feedback HTML.
     */
    private function handleGet(): string
    {
        $auth = new Auth($this->userRepository, $this->session);

        if ($auth->isAuthenticated()) {
            return $this->success('You are already signed in.');
        }

        return '';
    }

    /**
     * Handle the POST request.
     * Validate the request data and register the user.
     * @param array<string, mixed> $post
     */
    private function handlePost(array $post): string
    {
        $validationResult = $this->validation->validateRegister(
            $post['user_login'] ?? null,
            $post['user_mail'] ?? null,
            $post['user_password'] ?? null,
            $post['password_confirm'] ?? null
        );

        // If the validation failed, return the error message.
        if (!$validationResult->isValid()) {
            return $this->error((string) $validationResult->getFirstError());
        }

        $registerDto = $validationResult->getRegisterRequest();

        try {
            $auth = new Auth($this->userRepository, $this->session);
            $registered = $auth->register(
                $registerDto->getUsername(),
                $registerDto->getEmail(),
                $registerDto->getPassword()
            );

            if ($registered) {
                return $this->success('Account created successfully!');
            }

            return $this->error('Username or email is already taken.');
        } catch (Throwable $e) {
            return $this->error('An error occurred during registration. Please try again later.');
        }
    }

    /**
     * Return the success message.
     * @param string $message The success message.
     * @return string The feedback HTML.
     */
    private function success(string $message): string
    {
        return '<p class="feedback feedback-success" role="status">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p>';
    }

    /**
     * Return the error message.
     * @param string $message The error message.
     * @return string The feedback HTML.
     */
    private function error(string $message): string
    {
        return '<p class="feedback feedback-error" role="alert">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p>';
    }
}
