<?php

namespace Platform\Organization\Enums;

enum RunStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktiv',
            self::COMPLETED => 'Abgeschlossen',
            self::CANCELLED => 'Abgebrochen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'muted',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
