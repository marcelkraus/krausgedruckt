<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class ContactRequest
{
    /**
     * @Assert\NotBlank
     */
    protected $name = "";

    /**
     * @Assert\NotBlank
     * @Assert\Email
     */
    protected $email = "";

    /**
     * @Assert\NotBlank
     */
    protected $message = "";

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setEmail(string $email): void {
        $this->email = $email;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function getMessage(): string {
        return $this->message;
    }

    public function setMessage(string $message): void {
        $this->message = $message;
    }
}
