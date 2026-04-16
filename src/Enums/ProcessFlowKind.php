<?php

namespace Platform\Organization\Enums;

enum ProcessFlowKind: string
{
    case Sequence     = 'sequence';
    case Conditional  = 'conditional';
    case Exception    = 'exception';
    case LoopBack     = 'loop_back';
    case Compensation = 'compensation';

    public function label(): string
    {
        return match ($this) {
            self::Sequence     => 'Sequenz',
            self::Conditional  => 'Bedingt',
            self::Exception    => 'Ausnahme',
            self::LoopBack     => 'Rückkopplung',
            self::Compensation => 'Kompensation',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sequence     => 'secondary',
            self::Conditional  => 'info',
            self::Exception    => 'danger',
            self::LoopBack     => 'warning',
            self::Compensation => 'primary',
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
