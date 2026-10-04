<?php
namespace App\Application\DTO;

/**
 * Data Transfer Object for login requests.
 * Represent the login request payload, encapsulating the username and password.
 */
class LoginRequestDTO {
    private string $username;
    private string $password;

    public function __construct(string $username, string $password) {
        $this->username = $username;
        $this->password = $password;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getPassword(): string {
        return $this->password;
    }
}