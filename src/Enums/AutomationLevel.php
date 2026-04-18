<?php

namespace Platform\Organization\Enums;

enum AutomationLevel: string
{
    case HUMAN = 'human';
    case LLM_ASSISTED = 'llm_assisted';
    case LLM_AUTONOMOUS = 'llm_autonomous';
    case HYBRID = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::HUMAN => 'Human',
            self::LLM_ASSISTED => 'LLM-Assisted',
            self::LLM_AUTONOMOUS => 'LLM-Autonomous',
            self::HYBRID => 'Hybrid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HUMAN => 'muted',
            self::LLM_ASSISTED => 'info',
            self::LLM_AUTONOMOUS => 'success',
            self::HYBRID => 'warning',
        };
    }

    /**
     * Score weight for automation scoring (0-100).
     */
    public function scoreWeight(): int
    {
        return match ($this) {
            self::LLM_AUTONOMOUS => 100,
            self::LLM_ASSISTED => 85,
            self::HYBRID => 70,
            self::HUMAN => 0, // human score depends on complexity
        };
    }

    public function isLlm(): bool
    {
        return in_array($this, [self::LLM_ASSISTED, self::LLM_AUTONOMOUS, self::HYBRID]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
