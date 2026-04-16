<?php

namespace Platform\Organization\Enums;

enum ProcessGatewayType: string
{
    case Exclusive  = 'exclusive';
    case Parallel   = 'parallel';
    case Inclusive  = 'inclusive';
    case EventBased = 'event_based';

    public function label(): string
    {
        return match ($this) {
            self::Exclusive  => 'Exklusiv (XOR)',
            self::Parallel   => 'Parallel (AND)',
            self::Inclusive  => 'Inklusiv (OR)',
            self::EventBased => 'Ereignisbasiert',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Exclusive  => 'X',
            self::Parallel   => '+',
            self::Inclusive  => 'O',
            self::EventBased => '◇',
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
