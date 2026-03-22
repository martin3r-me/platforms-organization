<?php

namespace Platform\Organization\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Services\AiToolLoopRunner;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationReport;
use Platform\Organization\Models\OrganizationReportType;

class ReportGenerator
{
    /**
     * Generiert einen Bericht basierend auf ReportType und Entity.
     */
    public function generate(
        OrganizationReportType $type,
        OrganizationEntity $entity,
        Authenticatable $user,
        ?string $outputChannelOverride = null,
    ): OrganizationReport {
        $outputChannel = $outputChannelOverride ?? $type->output_channel;
        $now = now();

        // Report-Datensatz erstellen mit status=generating
        $report = OrganizationReport::create([
            'team_id' => $type->team_id,
            'report_type_id' => $type->id,
            'entity_id' => $entity->id,
            'user_id' => $user->getAuthIdentifier(),
            'snapshot_at' => $now,
            'status' => 'generating',
            'output_channel' => $outputChannel,
            'created_by' => $user->getAuthIdentifier(),
        ]);

        try {
            $systemPrompt = $this->buildSystemPrompt($type, $entity, $outputChannel);

            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => "Erstelle jetzt den Bericht \"{$type->name}\" für die Entity \"{$entity->name}\". "
                        . "Lade zuerst die benötigten Module-Tools via tools.GET, dann hole die Daten und fülle die Hülle aus.",
                ],
            ];

            $context = ToolContext::fromAuth();

            $runner = AiToolLoopRunner::make();
            $result = $runner->run($messages, 'gpt-4.1', $context, [
                'max_iterations' => 50,
                'max_output_tokens' => 16000,
                'max_output_continuations' => 5,
                'reasoning' => ['effort' => 'medium'],
            ]);

            $content = $result['assistant'] ?? '';

            if (empty(trim($content))) {
                $report->update([
                    'status' => 'failed',
                    'error_message' => 'AI hat keinen Inhalt generiert.',
                ]);
                return $report->refresh();
            }

            // Obsidian-Pfad bestimmen
            $obsidianPath = null;
            if (in_array($outputChannel, ['obsidian', 'all']) && $type->obsidian_folder) {
                $date = $now->format('Y-m-d');
                $entityCode = $entity->code ?? $entity->id;
                $obsidianPath = rtrim($type->obsidian_folder, '/') . "/{$date}-{$entityCode}-{$type->key}.md";
            }

            $report->update([
                'generated_content' => $content,
                'status' => 'final',
                'obsidian_path' => $obsidianPath,
                'metadata' => [
                    'iterations' => $result['iterations'] ?? null,
                    'tool_calls' => $result['all_tool_call_names'] ?? [],
                    'model' => 'gpt-4.1',
                ],
            ]);

            return $report->refresh();
        } catch (\Throwable $e) {
            Log::error('[ReportGenerator] Bericht-Generierung fehlgeschlagen', [
                'report_id' => $report->id,
                'report_type' => $type->key,
                'entity' => $entity->name,
                'error' => $e->getMessage(),
            ]);

            $report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return $report->refresh();
        }
    }

    /**
     * Baut den System-Prompt für den AI-Agent.
     */
    protected function buildSystemPrompt(
        OrganizationReportType $type,
        OrganizationEntity $entity,
        string $outputChannel,
    ): string {
        $hullMarkdown = $this->hullToMarkdown($type->hull);
        $requirementsText = $this->requirementsToText($type->requirements);
        $modulesText = implode(', ', $type->modules ?? []);

        $prompt = <<<PROMPT
Du bist ein Berichts-Agent. Du erstellst einen Bericht vom Typ "{$type->name}".
Entity: {$entity->name} (ID: {$entity->id}, Code: {$entity->code})
Team-ID: {$type->team_id}

HÜLLE (du musst exakt diese Abschnitte ausfüllen):
{$hullMarkdown}

PROMPT;

        if ($requirementsText) {
            $prompt .= <<<PROMPT

ANFORDERUNGEN:
{$requirementsText}

PROMPT;
        }

        $prompt .= <<<PROMPT

VERFÜGBARE MODULE: {$modulesText}
Nutze tools.GET(module="...") um die Tools der einzelnen Module zu laden.
Dann hole die relevanten Daten für Entity "{$entity->name}".
PROMPT;

        if ($type->include_time_entries) {
            $prompt .= <<<PROMPT

Lade auch Zeitbuchungen via organization.time_entries Tools (organization.time_entries.GET).
PROMPT;
        }

        if (in_array($outputChannel, ['obsidian', 'all']) && $type->obsidian_folder) {
            $date = now()->format('Y-m-d');
            $entityCode = $entity->code ?? $entity->id;
            $filename = "{$date}-{$entityCode}-{$type->key}.md";
            $prompt .= <<<PROMPT

Schreibe den fertigen Bericht als Markdown via Obsidian-MCP-Tool nach "{$type->obsidian_folder}".
Dateiname: {$filename}
PROMPT;
        }

        $prompt .= <<<PROMPT

WICHTIG:
- Antworte ausschließlich mit dem fertigen Bericht im Markdown-Format.
- Halte dich exakt an die vorgegebene Hülle/Gliederung.
- Nutze nur Daten, die du über die Tools abgerufen hast.
- Wenn für einen Abschnitt keine Daten vorliegen, schreibe "Keine Daten verfügbar."
PROMPT;

        return $prompt;
    }

    /**
     * Wandelt das hull-Array in eine Markdown-Struktur um.
     */
    protected function hullToMarkdown(array $hull, int $level = 1): string
    {
        $md = '';
        $prefix = str_repeat('#', $level);

        foreach ($hull as $key => $value) {
            if (is_array($value)) {
                // Nested section
                $title = is_string($key) ? $key : "Abschnitt " . ($key + 1);
                $md .= "{$prefix} {$title}\n";
                if (isset($value['description'])) {
                    $md .= "{$value['description']}\n\n";
                    unset($value['description']);
                }
                if (!empty($value)) {
                    $md .= $this->hullToMarkdown($value, $level + 1);
                }
            } else {
                // Simple section with description
                $title = is_string($key) ? $key : $value;
                $desc = is_string($key) ? $value : '';
                $md .= "{$prefix} {$title}\n";
                if ($desc) {
                    $md .= "{$desc}\n";
                }
                $md .= "\n";
            }
        }

        return $md;
    }

    /**
     * Wandelt das requirements-Array in lesbaren Text um.
     */
    protected function requirementsToText(?array $requirements): string
    {
        if (empty($requirements)) {
            return '';
        }

        $lines = [];
        foreach ($requirements as $key => $value) {
            if (is_string($value)) {
                $label = is_string($key) ? "{$key}: " : '- ';
                $lines[] = $label . $value;
            } elseif (is_array($value)) {
                $lines[] = is_string($key) ? "{$key}:" : '-';
                foreach ($value as $subKey => $subValue) {
                    $subLabel = is_string($subKey) ? "  {$subKey}: " : '  - ';
                    $lines[] = $subLabel . (is_string($subValue) ? $subValue : json_encode($subValue, JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return implode("\n", $lines);
    }
}
