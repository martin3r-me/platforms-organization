<?php

namespace Platform\Organization\Enums;

enum ProcessStatus: string
{
    case DRAFT = 'draft';
    case UNDER_REVIEW = 'under_review';
    case PILOT = 'pilot';
    case ACTIVE = 'active';
    case DEPRECATED = 'deprecated';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Entwurf',
            self::UNDER_REVIEW => 'In Prüfung',
            self::PILOT => 'Pilot',
            self::ACTIVE => 'Aktiv',
            self::DEPRECATED => 'Veraltet',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'muted',
            self::UNDER_REVIEW => 'warning',
            self::PILOT => 'info',
            self::ACTIVE => 'success',
            self::DEPRECATED => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
