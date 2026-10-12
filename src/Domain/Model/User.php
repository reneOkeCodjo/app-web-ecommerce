<?php 
namespace App\Domain\Model;
/**
 * Class User
 * Represents a user in the system.
 */
class User {
    private int $id;
    private string $username;
    private string $email;
    private string $password;

    /**
     * User constructor.
     *
     * @param int $id The ID of the user.
     * @param string $username The username of the user.
     * @param string $email The email of the user.
     * @param string $password The password of the user.
     */
    public function __construct(int $id, string $username, string $email, string $password) {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->password = $password;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function getPassword(): string {
        return $this->password;
    }
}