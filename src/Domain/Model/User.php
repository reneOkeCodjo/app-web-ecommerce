<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * Class User
 * Represents a user in the system.
 */
class User
{
    private int $id;
    private string $username;
    private string $email;
    private string $password;
    private string $compteType;
    private ?string $dateNew;
    private ?string $dateLogin;

    /**
     * User constructor.
     *
     * @param int $id The ID of the user.
     * @param string $username The username of the user.
     * @param string $email The email of the user.
     * @param string $password The password of the user.
     * @param string $compteType Account type (client, admin, vendeur, …).
     * @param string|null $dateNew Creation date, useful in the admin list.
     * @param string|null $dateLogin Last successful login date.
     */
    public function __construct(
        int $id,
        string $username,
        string $email,
        string $password,
        string $compteType = 'client',
        ?string $dateNew = null,
        ?string $dateLogin = null
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->password = $password;
        $this->compteType = $compteType;
        $this->dateNew = $dateNew;
        $this->dateLogin = $dateLogin;
    }

    public function getId(): int
    {
        return $this->id;
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

    public function getCompteType(): string
    {
        return $this->compteType;
    }

    public function getDateNew(): ?string
    {
        return $this->dateNew;
    }

    public function getDateLogin(): ?string
    {
        return $this->dateLogin;
    }

    public function isAdmin(): bool
    {
        return $this->compteType === 'admin';
    }
}
