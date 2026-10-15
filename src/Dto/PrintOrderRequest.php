<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PrintOrderRequest
{
    #[Assert\NotBlank(message: 'Bitte füge die Adresse deines Modells ein.')]
    #[Assert\Url(message: 'Das sieht nicht nach einer vollständigen Adresse aus.', requireTld: true)]
    #[Assert\Length(max: 500, maxMessage: 'Die Adresse ist zu lang.')]
    public string $url = '';

    #[Assert\NotNull(message: 'Bitte sag mir als Zahl, wie oft ich drucken soll.')]
    #[Assert\Positive(message: 'Es muss mindestens ein Stück sein.')]
    #[Assert\LessThanOrEqual(value: 999, message: 'Bei so vielen Stücken schreib mir bitte direkt – dafür rechne ich anders.')]
    public ?int $quantity = 1;

    /**
     * @var list<string>
     */
    // `All` would report under `context[0]`, which no field on the page shows.
    #[Assert\Choice(callback: [UsageContext::class, 'values'], multiple: true, multipleMessage: 'Diese Angabe kenne ich nicht.')]
    public array $context = [];

    #[Assert\Length(max: 60, maxMessage: 'Die Farbangabe ist zu lang.')]
    public string $color = '';

    #[Assert\Length(max: 3000, maxMessage: 'Deine Nachricht ist zu lang.')]
    public string $message = '';

    #[Assert\NotBlank(message: 'Ohne E-Mail-Adresse kann ich dir kein Angebot schicken.')]
    #[Assert\Email(message: 'Diese E-Mail-Adresse sieht nicht gültig aus.', mode: 'strict')]
    #[Assert\Length(max: 180, maxMessage: 'Die E-Mail-Adresse ist zu lang.')]
    public string $email = '';
}
