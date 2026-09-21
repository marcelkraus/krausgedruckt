<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Carries and validates an inquiry from the print-order assistant.
 *
 * Hand-rolled like the contact form – no symfony/form – so both paths
 * answer a submission through one mechanism instead of two.
 *
 * **Two fields are required, and they are the two the workshop cannot
 * work without:** the address of the model and somewhere to send the
 * offer. Everything else is the visitor telling us more than he has to.
 */
final class PrintOrderRequest
{
    #[Assert\NotBlank(message: 'Bitte füge die Adresse deines Modells ein.')]
    #[Assert\Url(message: 'Das sieht nicht nach einer vollständigen Adresse aus.', requireTld: true)]
    #[Assert\Length(max: 500, maxMessage: 'Die Adresse ist zu lang.')]
    public string $url = '';

    #[Assert\NotNull(message: 'Bitte sag uns als Zahl, wie oft wir drucken sollen.')]
    #[Assert\Positive(message: 'Es muss mindestens ein Stück sein.')]
    #[Assert\LessThanOrEqual(value: 999, message: 'Bei so vielen Stücken schreib uns bitte direkt – dafür rechnen wir anders.')]
    public ?int $quantity = 1;

    /**
     * @var list<string>
     */
    // `Choice` over the whole list, not `All` around a single one: `All`
    // reports under `context[0]`, and a violation keyed by an index has no
    // field on the page to be shown at.
    #[Assert\Choice(callback: [UsageContext::class, 'values'], multiple: true, multipleMessage: 'Diese Angabe kennen wir nicht.')]
    public array $context = [];

    #[Assert\Length(max: 60, maxMessage: 'Die Farbangabe ist zu lang.')]
    public string $color = '';

    #[Assert\Length(max: 3000, maxMessage: 'Deine Nachricht ist zu lang.')]
    public string $message = '';

    #[Assert\NotBlank(message: 'Ohne E-Mail-Adresse können wir dir kein Angebot schicken.')]
    #[Assert\Email(message: 'Diese E-Mail-Adresse sieht nicht gültig aus.', mode: 'strict')]
    #[Assert\Length(max: 180, maxMessage: 'Die E-Mail-Adresse ist zu lang.')]
    public string $email = '';
}
