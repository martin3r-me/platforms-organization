<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateSignalInferencePromptTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signal_inference_prompts.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/signal_inference_prompts/{id} - Aktualisiert einen Inference-Prompt. Nutze organization.signal_inference_prompts.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'inference_prompt_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Inference-Prompts.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung ("" zum Leeren).',
                ],
                'vsm_system' => [
                    'type' => 'string',
                    'description' => 'Optional: VSM-System (s1, s2, s3, s3_star, s4, s5).',
                ],
                'prompt_template' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue diagnostische Frage.',
                ],
                'data_sources' => [
                    'type' => 'array',
                    'description' => 'Optional: Neue Datenquellen.',
                    'items' => ['type' => 'string'],
                ],
                'dimension' => [
                    'type' => 'string',
                    'description' => 'Optional: Dimension (null zum Leeren).',
                ],
                'default_severity' => [
                    'type' => 'string',
                    'description' => 'Optional: info, warning, critical.',
                ],
                'scope_type' => [
                    'type' => 'string',
                    'description' => 'Optional: all, entity_type, subtree.',
                ],
                'scope_value' => [
                    'type' => 'array',
                    'description' => 'Optional: Neue Scope-Werte (null zum Leeren).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
                'schedule_interval_hours' => [
                    'type' => 'integer',
                    'description' => 'Optional: Evaluierungs-Intervall in Stunden (24=täglich, 72=~2x/Woche, 168=wöchentlich). null = globaler Default.',
                ],
            ],
            'required' => ['inference_prompt_id'],
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
                'inference_prompt_id',
                OrganizationSignalInferencePrompt::class,
                'NOT_FOUND',
                'Inference-Prompt nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationSignalInferencePrompt $prompt */
            $prompt = $found['model'];

            if ((int) $prompt->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Inference-Prompt gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];

            if (array_key_exists('name', $arguments)) {
                $name = trim((string) ($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }

            if (array_key_exists('description', $arguments)) {
                $d = (string) ($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }

            if (array_key_exists('vsm_system', $arguments)) {
                $vs = $arguments['vsm_system'];
                if (! in_array($vs, ['s1', 's2', 's3', 's3_star', 's4', 's5'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'vsm_system muss s1, s2, s3, s3_star, s4 oder s5 sein.');
                }
                $update['vsm_system'] = $vs;
            }

            if (array_key_exists('prompt_template', $arguments)) {
                $pt = trim((string) ($arguments['prompt_template'] ?? ''));
                if ($pt === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'prompt_template darf nicht leer sein.');
                }
                $update['prompt_template'] = $pt;
            }

            if (array_key_exists('data_sources', $arguments)) {
                $update['data_sources'] = $arguments['data_sources'];
            }

            if (array_key_exists('dimension', $arguments)) {
                $update['dimension'] = $arguments['dimension'];
            }

            if (array_key_exists('default_severity', $arguments)) {
                $s = $arguments['default_severity'];
                if (! in_array($s, ['info', 'warning', 'critical'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'default_severity muss info, warning oder critical sein.');
                }
                $update['default_severity'] = $s;
            }

            if (array_key_exists('scope_type', $arguments)) {
                $st = $arguments['scope_type'];
                if (! in_array($st, ['all', 'entity_type', 'subtree'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'scope_type muss all, entity_type oder subtree sein.');
                }
                $update['scope_type'] = $st;
            }

            if (array_key_exists('scope_value', $arguments)) {
                $update['scope_value'] = $arguments['scope_value'];
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (array_key_exists('schedule_interval_hours', $arguments)) {
                $update['schedule_interval_hours'] = $arguments['schedule_interval_hours'] !== null
                    ? (int) $arguments['schedule_interval_hours']
                    : null;
            }

            if (! empty($update)) {
                $prompt->update($update);
            }
            $prompt->refresh();

            return ToolResult::success([
                'id' => $prompt->id,
                'uuid' => $prompt->uuid,
                'name' => $prompt->name,
                'vsm_system' => $prompt->vsm_system,
                'prompt_template' => $prompt->prompt_template,
                'data_sources' => $prompt->data_sources,
                'dimension' => $prompt->dimension,
                'default_severity' => $prompt->default_severity,
                'scope_type' => $prompt->scope_type,
                'is_active' => (bool) $prompt->is_active,
                'schedule_interval_hours' => $prompt->schedule_interval_hours,
                'message' => 'Inference-Prompt erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Inference-Prompts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'inference', 'vsm', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
