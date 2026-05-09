<?php

namespace Platform\Organization\Enums;

enum SavingsType: string
{
    case COST_REDUCTION = 'cost_reduction';
    case PRODUCTIVITY_GAIN = 'productivity_gain';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            self::COST_REDUCTION => 'Echte Kosteneinsparung',
            self::PRODUCTIVITY_GAIN => 'Produktivitätsgewinn',
            self::BOTH => 'Beides',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::COST_REDUCTION => 'success',
            self::PRODUCTIVITY_GAIN => 'info',
            self::BOTH => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
