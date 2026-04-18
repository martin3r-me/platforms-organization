<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Enums\ImprovementStatus;
use Platform\Organization\Models\OrganizationProcessImprovement;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessImprovementTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_improvements.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/process_improvements/{id} - Aktualisiert eine Verbesserung. Status-Workflow: identified → planned → in_progress → completed → under_observation → validated/failed. Alternativ: on_hold (Pause), rejected (verworfen).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'           => ['type' => 'integer'],
                'improvement_id'    => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'title'             => ['type' => 'string'],
                'description'       => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'category'          => ['type' => 'string', 'description' => 'cost | quality | speed | risk | standardization.'],
                'priority'          => ['type' => 'string', 'description' => 'low | medium | high | critical.'],
                'status'            => ['type' => 'string', 'description' => 'identified | planned | in_progress | on_hold | completed | under_observation | validated | failed | rejected.'],
                'expected_outcome'  => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'actual_outcome'    => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'after_snapshot_id' => ['type' => 'integer', 'description' => 'Snapshot-ID für Nachher-Zustand. 0 oder null zum Leeren.'],
                'target_step_id'                    => ['type' => 'integer', 'description' => 'Ziel-Step-ID. 0 oder null zum Leeren.'],
                'projected_duration_target_minutes' => ['type' => 'integer', 'description' => 'Projizierte Dauer in Minuten. 0 oder null zum Leeren.'],
                'projected_automation_level'        => ['type' => 'string', 'description' => 'human | llm_assisted | llm_autonomous | hybrid. "" zum Leeren.'],
                'projected_complexity'              => ['type' => 'string', 'description' => 'xs | s | m | l | xl | xxl. "" zum Leeren.'],
                'projected_hourly_rate'             => ['type' => 'number', 'description' => 'Projizierter Stundensatz in EUR. 0 oder null zum Leeren.'],
                'metadata'                          => ['type' => 'object'],
            ],
            'required' => ['improvement_id'],
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
                'improvement_id',
                OrganizationProcessImprovement::class,
                'NOT_FOUND',
                'Verbesserung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessImprovement $improvement */
            $improvement = $found['model'];
            if ((int) $improvement->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Verbesserung gehört nicht zum Team.');
            }

            $update = [];

            if (array_key_exists('title', $arguments)) {
                $val = trim((string) ($arguments['title'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'title darf nicht leer sein.');
                }
                $update['title'] = $val;
            }

            foreach (['description', 'expected_outcome', 'actual_outcome'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }

            if (array_key_exists('category', $arguments)) {
                $val = (string) $arguments['category'];
                if (! in_array($val, ['cost', 'quality', 'speed', 'risk', 'standardization'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültige category.');
                }
                $update['category'] = $val;
            }

            if (array_key_exists('priority', $arguments)) {
                $val = (string) $arguments['priority'];
                if (! in_array($val, ['low', 'medium', 'high', 'critical'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültige priority.');
                }
                $update['priority'] = $val;
            }

            if (array_key_exists('status', $arguments)) {
                $val = (string) $arguments['status'];
                $statusEnum = ImprovementStatus::tryFrom($val);
                if (! $statusEnum) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiger status.');
                }
                $update['status'] = $val;
                if ($statusEnum->isCompleted() && ! $improvement->completed_at) {
                    $update['completed_at'] = now();
                }
                if (! $statusEnum->isCompleted()) {
                    $update['completed_at'] = null;
                }
            }

            if (array_key_exists('target_step_id', $arguments)) {
                $val = $arguments['target_step_id'];
                $update['target_step_id'] = (! empty($val) && (int) $val > 0) ? (int) $val : null;
            }

            if (array_key_exists('projected_duration_target_minutes', $arguments)) {
                $val = $arguments['projected_duration_target_minutes'];
                $update['projected_duration_target_minutes'] = (! empty($val) && (int) $val > 0) ? (int) $val : null;
            }

            if (array_key_exists('projected_automation_level', $arguments)) {
                $val = (string) ($arguments['projected_automation_level'] ?? '');
                $update['projected_automation_level'] = $val !== '' ? $val : null;
            }

            if (array_key_exists('projected_complexity', $arguments)) {
                $val = (string) ($arguments['projected_complexity'] ?? '');
                $update['projected_complexity'] = $val !== '' ? $val : null;
            }

            if (array_key_exists('projected_hourly_rate', $arguments)) {
                $val = $arguments['projected_hourly_rate'];
                $update['projected_hourly_rate'] = (! empty($val) && (float) $val > 0) ? (float) $val : null;
            }

            if (array_key_exists('after_snapshot_id', $arguments)) {
                $val = $arguments['after_snapshot_id'];
                $update['after_snapshot_id'] = (! empty($val) && (int) $val > 0) ? (int) $val : null;
            }

            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = $arguments['metadata'];
            }

            if (! empty($update)) {
                $improvement->update($update);
            }
            $improvement->refresh();

            return ToolResult::success([
                'id'       => $improvement->id,
                'uuid'     => $improvement->uuid,
                'title'    => $improvement->title,
                'status'   => $improvement->status?->value,
                'priority' => $improvement->priority,
                'category' => $improvement->category,
                'message'  => 'Verbesserung aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Verbesserung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'processes', 'improvements', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
