<?php

namespace Platform\Organization\Enums;

enum ProcessEventType: string
{
    case Start             = 'start';
    case End               = 'end';
    case IntermediateThrow = 'intermediate_throw';
    case IntermediateCatch = 'intermediate_catch';
    case Timer             = 'timer';
    case Message           = 'message';
    case Error             = 'error';
    case Escalation        = 'escalation';

    public function label(): string
    {
        return match ($this) {
            self::Start             => 'Start',
            self::End               => 'Ende',
            self::IntermediateThrow => 'Zwischenereignis (werfend)',
            self::IntermediateCatch => 'Zwischenereignis (fangend)',
            self::Timer             => 'Zeitgeber',
            self::Message           => 'Nachricht',
            self::Error             => 'Fehler',
            self::Escalation        => 'Eskalation',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Start             => 'success',
            self::End               => 'secondary',
            self::IntermediateThrow => 'info',
            self::IntermediateCatch => 'info',
            self::Timer             => 'warning',
            self::Message           => 'primary',
            self::Error             => 'danger',
            self::Escalation        => 'danger',
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
