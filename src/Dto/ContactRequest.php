<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Carries and validates the data submitted through the contact form.
 *
 * The form is hand-rolled — no symfony/form — the same way the sister
 * project does it, so both sites answer a submission through one mechanism
 * instead of two. This object is filled from the request and handed to
 * symfony/validator.
 *
 * Constraints are attributes, never doc-block annotations: Symfony 8 does
 * not read the latter, and the class once shipped with `@Assert\Email` in a
 * doc block, which let an invalid address through into the mailer.
 */
final class ContactRequest
{
    #[Assert\NotBlank(message: 'Bitte sag uns, wie du heißt.')]
    #[Assert\Length(max: 120, maxMessage: 'Der Name ist zu lang.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Ohne E-Mail-Adresse können wir dir nicht antworten.')]
    #[Assert\Email(message: 'Diese E-Mail-Adresse sieht nicht gültig aus.', mode: 'strict')]
    #[Assert\Length(max: 180, maxMessage: 'Die E-Mail-Adresse ist zu lang.')]
    public string $email = '';

    #[Assert\Length(max: 40, maxMessage: 'Die Telefonnummer ist zu lang.')]
    public string $phone = '';

    #[Assert\Length(max: 60, maxMessage: 'Der Rabattcode ist zu lang.')]
    public string $discountCode = '';

    #[Assert\NotBlank(message: 'Erzähl uns kurz, worum es geht.')]
    #[Assert\Length(min: 10, max: 3000, minMessage: 'Erzähl uns bitte etwas ausführlicher, worum es geht.', maxMessage: 'Deine Nachricht ist zu lang.')]
    public string $message = '';
}
