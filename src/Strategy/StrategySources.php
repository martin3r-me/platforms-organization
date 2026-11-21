<?php

namespace Platform\Organization\Strategy;

use Platform\Organization\Contracts\StrategySource;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Registry der Strategie-Quelle (lose Kopplung, analog EntityLinkProvider): Das Management Book
 * registriert sich beim Booten. Ohne registrierte Quelle liefern alle Leser leer bzw. null –
 * die Organisation bleibt ohne Management Book voll lauffähig.
 */
class StrategySources
{
    private static ?StrategySource $source = null;

    public static function register(StrategySource $source): void
    {
        self::$source = $source;
    }

    public static function has(): bool
    {
        return self::$source !== null;
    }

    public static function forEntity(OrganizationEntity $entity): ?array
    {
        if (! self::$source) {
            return null;
        }
        try {
            return self::$source->forEntity($entity);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** @param array<int, array{0:int,1:int}> $slots */
    public static function milestonesForTeam(int $teamId, array $slots): array
    {
        return self::$source ? self::$source->milestonesForTeam($teamId, $slots) : [];
    }

    public static function contribute(int $teamId, int $milestoneId, string $linkableType, int $linkableId, ?int $weight, ?int $userId): ?array
    {
        return self::$source ? self::$source->contribute($teamId, $milestoneId, $linkableType, $linkableId, $weight, $userId) : null;
    }

    /** @param array<string, int[]> $idsByAlias */
    public static function alignmentsFor(array $idsByAlias): array
    {
        if (! self::$source || $idsByAlias === []) {
            return [];
        }
        try {
            return self::$source->alignmentsFor($idsByAlias);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
