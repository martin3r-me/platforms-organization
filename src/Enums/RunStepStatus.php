<?php

namespace Platform\Organization\Enums;

enum RunStepStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Offen',
            self::COMPLETED => 'Erledigt',
            self::SKIPPED => 'Übersprungen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'muted',
            self::COMPLETED => 'success',
            self::SKIPPED => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
