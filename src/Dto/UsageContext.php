<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Situations rather than material properties: a customer knows where the part
 * goes; the workshop reads the material out of it.
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
     * @return list<self>
     */
    public static function inDisplayOrder(): array
    {
        return [self::Decoration, self::Flexible, self::Sun, self::Heat, self::Load, self::Function];
    }
}
