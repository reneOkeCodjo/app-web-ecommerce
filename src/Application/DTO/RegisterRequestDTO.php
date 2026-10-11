<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * Data Transfer Object for registration requests.
 */
class RegisterRequestDTO
{
    public function __construct(
        private string $username,
        private string $email,
        private string $password
    ) {
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
