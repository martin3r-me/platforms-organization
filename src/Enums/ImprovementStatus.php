<?php

namespace Platform\Organization\Enums;

enum ImprovementStatus: string
{
    case IDENTIFIED = 'identified';
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case UNDER_OBSERVATION = 'under_observation';
    case VALIDATED = 'validated';
    case FAILED = 'failed';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::IDENTIFIED => 'Identifiziert',
            self::PLANNED => 'Geplant',
            self::IN_PROGRESS => 'In Arbeit',
            self::ON_HOLD => 'Pausiert',
            self::COMPLETED => 'Umgesetzt',
            self::UNDER_OBSERVATION => 'In Beobachtung',
            self::VALIDATED => 'Validiert',
            self::FAILED => 'Wirkungslos',
            self::REJECTED => 'Abgelehnt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::IDENTIFIED => 'muted',
            self::PLANNED => 'info',
            self::IN_PROGRESS => 'warning',
            self::ON_HOLD => 'muted',
            self::COMPLETED => 'info',
            self::UNDER_OBSERVATION => 'warning',
            self::VALIDATED => 'success',
            self::FAILED => 'danger',
            self::REJECTED => 'danger',
        };
    }

    /**
     * States that imply the improvement has been implemented.
     */
    public function isCompleted(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::UNDER_OBSERVATION,
            self::VALIDATED,
            self::FAILED,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
