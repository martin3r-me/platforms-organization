<?php

namespace Platform\Organization\Enums;

enum ProcessFrequency: string
{
    case RARE = 'rare';
    case OCCASIONAL = 'occasional';
    case REGULAR = 'regular';
    case FREQUENT = 'frequent';
    case VERY_FREQUENT = 'very_frequent';

    public function label(): string
    {
        return match ($this) {
            self::RARE => 'Selten (~6×/Jahr)',
            self::OCCASIONAL => 'Gelegentlich (~1×/Monat)',
            self::REGULAR => 'Regelmäßig (~1×/Woche)',
            self::FREQUENT => 'Häufig (~1×/Tag)',
            self::VERY_FREQUENT => 'Sehr häufig (mehrfach/Tag)',
        };
    }

    public function monthlyFactor(): float
    {
        return match ($this) {
            self::RARE => 0.5,
            self::OCCASIONAL => 1.0,
            self::REGULAR => 4.0,
            self::FREQUENT => 22.0,
            self::VERY_FREQUENT => 60.0,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
