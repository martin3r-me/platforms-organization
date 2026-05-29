<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteSignalInferencePromptTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signal_inference_prompts.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/signal_inference_prompts/{id} - Löscht einen Inference-Prompt (soft delete).';
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
            ],
            'required' => ['inference_prompt_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
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

            $prompt->delete();

            return ToolResult::success([
                'id' => $prompt->id,
                'message' => 'Inference-Prompt gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Inference-Prompts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'inference', 'vsm', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
