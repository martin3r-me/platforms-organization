<?php

namespace Platform\Organization\Enums;

enum ProcessChainType: string
{
    case ValueStream = 'value_stream';
    case EndToEnd    = 'end_to_end';
    case SubChain    = 'sub_chain';
    case AdHoc       = 'ad_hoc';

    public function label(): string
    {
        return match ($this) {
            self::ValueStream => 'Wertstrom',
            self::EndToEnd    => 'End-to-End',
            self::SubChain    => 'Teilkette',
            self::AdHoc       => 'Ad-hoc',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ValueStream => 'success',
            self::EndToEnd    => 'primary',
            self::SubChain    => 'info',
            self::AdHoc       => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ValueStream => 'heroicon-o-sparkles',
            self::EndToEnd    => 'heroicon-o-arrows-right-left',
            self::SubChain    => 'heroicon-o-link',
            self::AdHoc       => 'heroicon-o-squares-2x2',
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
