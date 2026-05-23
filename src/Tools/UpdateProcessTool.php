<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.processes.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/processes/{id} - Aktualisiert eine Prozess-Definition.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'         => ['type' => 'integer'],
                'process_id'      => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'name'            => ['type' => 'string'],
                'code'            => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'description'     => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'owner_entity_id' => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'status'          => ['type' => 'string', 'description' => 'draft | under_review | pilot | active | deprecated.'],
                'version'         => ['type' => 'integer'],
                'is_active'       => ['type' => 'boolean'],
                'metadata'              => ['type' => 'object'],
                'process_landscape'     => ['type' => 'string', 'description' => 'Prozesslandkarte: Einordnung in die Gesamtlandschaft. "" zum Leeren.'],
                'corefit_classification_notes' => ['type' => 'string', 'description' => 'COREFIT Klassifizierung: Begründung der Einstufung. "" zum Leeren.'],
                'target_description'    => ['type' => 'string', 'description' => 'Zielbild. "" zum Leeren.'],
                'value_proposition'     => ['type' => 'string', 'description' => 'Kundennutzen & Wertbeitrag. "" zum Leeren.'],
                'cost_analysis'         => ['type' => 'string', 'description' => 'Kosten & Break-Even. "" zum Leeren.'],
                'risk_assessment'       => ['type' => 'string', 'description' => 'Risiko & Resilienz. "" zum Leeren.'],
                'improvement_levers'    => ['type' => 'string', 'description' => 'Hebel & Lösungsdesign. "" zum Leeren.'],
                'action_plan'           => ['type' => 'string', 'description' => 'Maßnahmenplan. "" zum Leeren.'],
                'standardization_notes' => ['type' => 'string', 'description' => 'Standardisierung & Kontrolle. "" zum Leeren.'],
                'hourly_rate'           => ['type' => 'number', 'description' => 'Stundensatz in EUR. 0 oder null zum Leeren.'],
                'workshop_notes'        => ['type' => 'array', 'description' => 'Workshop-Notizen als JSON-Array. Jede Note: {id, type, title, content, x, y, width, height, color, metadata}. null oder [] zum Leeren.'],
            ],
            'required' => ['process_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'process_id',
                OrganizationProcess::class,
                'NOT_FOUND',
                'Prozess nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcess $process */
            $process = $found['model'];
            if ((int) $process->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $val = trim((string) ($arguments['name'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $val;
            }
            foreach (['code', 'description'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }
            foreach (['owner_entity_id'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = $arguments[$field];
                    $update[$field] = (! empty($val) && (int) $val > 0) ? (int) $val : null;
                }
            }
            if (array_key_exists('status', $arguments)) {
                $update['status'] = (string) $arguments['status'];
            }
            if (array_key_exists('version', $arguments)) {
                $update['version'] = (int) $arguments['version'];
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = $arguments['metadata'];
            }
            foreach (['process_landscape', 'corefit_classification_notes', 'target_description', 'value_proposition', 'cost_analysis', 'risk_assessment', 'improvement_levers', 'action_plan', 'standardization_notes'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }
            if (array_key_exists('workshop_notes', $arguments)) {
                $val = $arguments['workshop_notes'];
                if (is_array($val) && !empty($val)) {
                    // Auto-compute block_key for each note based on position
                    foreach ($val as &$note) {
                        if (!isset($note['block_key']) && isset($note['x'], $note['y'])) {
                            $note['block_key'] = self::resolveBlockKey((int) $note['x'], (int) $note['y']);
                        }
                    }
                    unset($note);
                    $update['workshop_notes'] = $val;
                } else {
                    $update['workshop_notes'] = null;
                }
            }
            if (array_key_exists('hourly_rate', $arguments)) {
                $val = $arguments['hourly_rate'];
                $update['hourly_rate'] = (! empty($val) && (float) $val > 0) ? round((float) $val, 2) : null;
            }

            if (! empty($update)) {
                $process->update($update);
            }
            $process->refresh();

            return ToolResult::success([
                'id'      => $process->id,
                'uuid'    => $process->uuid,
                'name'    => $process->name,
                'code'    => $process->code,
                'status'  => $process->status,
                'version' => $process->version,
                'team_id' => $process->team_id,
                'message' => 'Prozess erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Prozesses: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'processes', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }

    /**
     * Resolve block_key from absolute board coordinates (fixed 3×3 grid).
     */
    private static function resolveBlockKey(int $x, int $y): ?string
    {
        $gridW = 1200;
        $gridH = 840;
        $boardW = 5000;
        $boardH = 3000;
        $cols = 3;
        $rows = 3;

        $gridLeft = ($boardW - $gridW) / 2;
        $gridTop = ($boardH - $gridH) / 2;
        $cellW = $gridW / $cols;
        $cellH = $gridH / $rows;

        $col = (int) floor(($x - $gridLeft) / $cellW);
        $row = (int) floor(($y - $gridTop) / $cellH);

        $grid = [
            ['target_description', 'value_proposition', 'process_landscape'],
            ['corefit_classification_notes', 'cost_analysis', 'risk_assessment'],
            ['improvement_levers', 'action_plan', 'standardization_notes'],
        ];

        if ($col >= 0 && $col < $cols && $row >= 0 && $row < $rows) {
            return $grid[$row][$col];
        }

        return null;
    }
}
