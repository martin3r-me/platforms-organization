<?php

namespace Platform\Organization\Enums;

enum ProcessCategory: string
{
    case Core = 'core';
    case Support = 'support';
    case Management = 'management';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Kernprozess',
            self::Support => 'Supportprozess',
            self::Management => 'Managementprozess',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Core => 'heroicon-o-bolt',
            self::Support => 'heroicon-o-wrench-screwdriver',
            self::Management => 'heroicon-o-chart-bar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Core => 'primary',
            self::Support => 'secondary',
            self::Management => 'info',
        };
    }
}
