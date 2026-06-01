<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalComment;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CommentSignalTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signals.comment';
    }

    public function getDescription(): string
    {
        return 'POST /organization/signals/{id}/comment - Fügt einen Kommentar zu einem Signal hinzu. Unterstützt Threading über parent_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'signal_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Signals.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Kommentar-Text.',
                ],
                'parent_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID eines bestehenden Kommentars für Threading.',
                ],
            ],
            'required' => ['signal_id', 'content'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $signalId = (int) ($arguments['signal_id'] ?? 0);
            if ($signalId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'signal_id ist erforderlich.');
            }

            $signal = OrganizationSignal::where('id', $signalId)
                ->where('team_id', $rootTeamId)
                ->first();

            if (! $signal) {
                return ToolResult::error('NOT_FOUND', 'Signal nicht gefunden.');
            }

            $content = trim((string) ($arguments['content'] ?? ''));
            if ($content === '') {
                return ToolResult::error('VALIDATION_ERROR', 'content ist erforderlich.');
            }

            // Validate parent_id if provided
            $parentId = ! empty($arguments['parent_id']) ? (int) $arguments['parent_id'] : null;
            if ($parentId) {
                $parentComment = OrganizationSignalComment::where('id', $parentId)
                    ->where('signal_id', $signalId)
                    ->first();

                if (! $parentComment) {
                    return ToolResult::error('NOT_FOUND', 'Parent-Kommentar nicht gefunden oder gehört nicht zu diesem Signal.');
                }
            }

            $comment = OrganizationSignalComment::create([
                'signal_id' => $signalId,
                'parent_id' => $parentId,
                'user_id' => $context->user?->id,
                'author_context' => 'user',
                'content' => $content,
            ]);

            return ToolResult::success([
                'id' => $comment->id,
                'uuid' => $comment->uuid,
                'signal_id' => $signalId,
                'message' => 'Kommentar erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Kommentars: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'signals', 'comment', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
