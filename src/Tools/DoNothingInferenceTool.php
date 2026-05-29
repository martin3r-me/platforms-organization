<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class DoNothingInferenceTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.inference.do_nothing';
    }

    public function getDescription(): string
    {
        return 'Explizites "alles in Ordnung" — keine Anomalien erkannt. Wird protokolliert. Nutze dies wenn die diagnostische Analyse keine Auffälligkeiten ergibt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Kurze Begründung warum alles in Ordnung ist.',
                ],
            ],
            'required' => ['reason'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $reason = trim((string) ($arguments['reason'] ?? ''));
        if ($reason === '') {
            return ToolResult::error('VALIDATION_ERROR', 'reason ist erforderlich.');
        }

        return ToolResult::success([
            'action' => 'do_nothing',
            'reason' => $reason,
            'message' => 'Diagnostische Entscheidung protokolliert: Keine Auffälligkeiten.',
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'inference', 'diagnostic'],
            'read_only' => false,
            'requires_auth' => false,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
