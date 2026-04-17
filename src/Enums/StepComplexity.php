<?php

namespace Platform\Organization\Enums;

enum StepComplexity: string
{
    case XS = 'xs';
    case S = 's';
    case M = 'm';
    case L = 'l';
    case XL = 'xl';
    case XXL = 'xxl';

    public function label(): string
    {
        return match ($this) {
            self::XS => 'XS – Trivial',
            self::S => 'S – Einfach',
            self::M => 'M – Mittel',
            self::L => 'L – Komplex',
            self::XL => 'XL – Sehr komplex',
            self::XXL => 'XXL – Extrem komplex',
        };
    }

    public function points(): int
    {
        return match ($this) {
            self::XS => 1,
            self::S => 2,
            self::M => 3,
            self::L => 5,
            self::XL => 8,
            self::XXL => 13,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
