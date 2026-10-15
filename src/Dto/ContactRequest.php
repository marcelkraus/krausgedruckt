<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactRequest
{
    #[Assert\NotBlank(message: 'Bitte sag mir, wie du heißt.')]
    #[Assert\Length(max: 120, maxMessage: 'Der Name ist zu lang.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Ohne E-Mail-Adresse kann ich dir nicht antworten.')]
    #[Assert\Email(message: 'Diese E-Mail-Adresse sieht nicht gültig aus.', mode: 'strict')]
    #[Assert\Length(max: 180, maxMessage: 'Die E-Mail-Adresse ist zu lang.')]
    public string $email = '';

    #[Assert\Length(max: 40, maxMessage: 'Die Telefonnummer ist zu lang.')]
    public string $phone = '';

    #[Assert\Length(max: 60, maxMessage: 'Der Rabattcode ist zu lang.')]
    public string $discountCode = '';

    #[Assert\NotBlank(message: 'Erzähl mir kurz, worum es geht.')]
    #[Assert\Length(min: 10, max: 3000, minMessage: 'Erzähl mir bitte etwas ausführlicher, worum es geht.', maxMessage: 'Deine Nachricht ist zu lang.')]
    public string $message = '';
}
