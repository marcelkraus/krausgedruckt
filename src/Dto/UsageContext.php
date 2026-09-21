<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * What the visitor wants his part for.
 *
 * Six situations instead of a scale of material properties: a customer
 * knows where his part goes, not how much heat resistance it needs. The
 * workshop reads the material out of them – PLA for decoration, PETG
 * where it must stay flexible, ASA for sun and for heat, ASA or PC-CF
 * where it has to take real load, and a faster print where the surface
 * does not matter.
 *
 * The labels live here because they are the question, not decoration – the
 * mail needs the same words the page showed.
 */
enum UsageContext: string
{
    case Decoration = 'decoration';
    case Flexible = 'flexible';
    case Function = 'function';
    case Heat = 'heat';
    case Load = 'load';
    case Sun = 'sun';

    public function label(): string
    {
        return match ($this) {
            self::Decoration => 'Ist Deko oder Spielzeug',
            self::Flexible => 'Muss flexibel bleiben',
            self::Function => 'Funktion ist wichtiger als Optik',
            self::Heat => 'Muss hohe Temperaturen aushalten können',
            self::Load => 'Muss richtig stabil werden',
            self::Sun => 'Wird in der Sonne eingesetzt',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * The order the page shows them in – the everyday case first, then the
     * four demands on the material, and last the one that is about the
     * print rather than about the part.
     *
     * @return list<self>
     */
    public static function inDisplayOrder(): array
    {
        return [self::Decoration, self::Flexible, self::Sun, self::Heat, self::Load, self::Function];
    }
}
