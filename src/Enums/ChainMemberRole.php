<?php

namespace Platform\Organization\Enums;

enum ChainMemberRole: string
{
    case Entry    = 'entry';
    case Middle   = 'middle';
    case Exit     = 'exit';
    case Optional = 'optional';

    public function label(): string
    {
        return match ($this) {
            self::Entry    => 'Einstieg',
            self::Middle   => 'Mittel',
            self::Exit     => 'Ausstieg',
            self::Optional => 'Optional',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Entry    => 'success',
            self::Middle   => 'secondary',
            self::Exit     => 'primary',
            self::Optional => 'info',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
