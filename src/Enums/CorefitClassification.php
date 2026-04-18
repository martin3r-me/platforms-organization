<?php

namespace Platform\Organization\Enums;

enum CorefitClassification: string
{
    case CORE = 'core';
    case CONTEXT = 'context';
    case NO_FIT = 'no_fit';

    public function label(): string
    {
        return match ($this) {
            self::CORE => 'Core',
            self::CONTEXT => 'Context',
            self::NO_FIT => 'No Fit',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CORE => 'success',
            self::CONTEXT => 'warning',
            self::NO_FIT => 'danger',
        };
    }

    public function hexColor(): string
    {
        return match ($this) {
            self::CORE => '#22c55e',
            self::CONTEXT => '#eab308',
            self::NO_FIT => '#ef4444',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
