<?php

namespace Platform\Organization\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Services\AiToolLoopRunner;
use Platform\Core\Services\OpenAiService;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Tools\ToolRegistry;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
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
            if ($type->usesTemplateEngine()) {
                return $this->generateFromTemplate($report, $type, $entity, $outputChannel, $now);
            }

            return $this->generateFromAiLoop($report, $type, $entity, $outputChannel, $now);
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
     * Template-Engine: Deterministische Datenabfrage + gezielte AI-Abschnitte + Blade-Rendering.
     */
    protected function generateFromTemplate(
        OrganizationReport $report,
        OrganizationReportType $type,
        OrganizationEntity $entity,
        string $outputChannel,
        \Carbon\Carbon $now,
    ): OrganizationReport {
        $context = ToolContext::fromAuth();
        $registry = app(ToolRegistry::class);
        $toolExecutor = new ToolExecutor($registry);

        // Phase 1: Zeitraum berechnen
        $periodResolver = new ReportPeriodResolver();
        $period = $periodResolver->resolve($type->frequency ?? 'manual');

        // Phase 0: Entity-Kontext auflösen (Entity + Kinder rekursiv)
        $entityContext = $this->resolveEntityContext($entity);

        $templateData = [
            'entity' => $entity,
            'entity_context' => $entityContext,
            'period_from' => $period['from']->toDateString(),
            'period_to' => $period['to']->toDateString(),
            'period_from_iso' => $period['from']->toIso8601String(),
            'period_to_iso' => $period['to']->toIso8601String(),
            'report_type' => $type,
            'generated_at' => $now->toIso8601String(),
        ];

        $toolCallNames = [];

        // Phase 1: Daten holen via ToolExecutor
        foreach ($type->data_sources ?? [] as $source) {
            $key = $source['key'] ?? null;
            $toolName = $source['tool'] ?? null;
            if (!$key || !$toolName) {
                continue;
            }

            $params = $this->resolveParams($source['params'] ?? [], $entity, $period, $entityContext);

            try {
                $result = $toolExecutor->execute($toolName, $params, $context);
                $toolCallNames[] = $toolName;

                if ($result->success) {
                    $templateData[$key] = $result->data['data'] ?? $result->data;
                } else {
                    Log::warning("[ReportGenerator] Tool {$toolName} fehlgeschlagen", [
                        'report_id' => $report->id,
                        'error' => $result->metadata['message'] ?? 'Unbekannt',
                    ]);
                    $templateData[$key] = [];
                }
            } catch (\Throwable $e) {
                Log::warning("[ReportGenerator] Tool {$toolName} Exception", [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
                $templateData[$key] = [];
            }
        }

        // Phase 2: AI-Abschnitte generieren
        $openAi = app(OpenAiService::class);

        foreach ($type->ai_sections ?? [] as $section) {
            $sectionKey = $section['key'] ?? null;
            $instruction = $section['instruction'] ?? null;
            if (!$sectionKey || !$instruction) {
                continue;
            }

            $basedOn = $section['based_on'] ?? [];
            $relevantData = [];
            foreach ($basedOn as $dataKey) {
                if (isset($templateData[$dataKey])) {
                    $relevantData[$dataKey] = $templateData[$dataKey];
                }
            }

            $maxTokens = (int) ($section['max_tokens'] ?? 500);

            try {
                $messages = [
                    [
                        'role' => 'system',
                        'content' => "Du bist ein professioneller Berichts-Autor. Entity: {$entity->name}. Zeitraum: {$templateData['period_from']} bis {$templateData['period_to']}.",
                    ],
                    [
                        'role' => 'user',
                        'content' => "Daten:\n" . json_encode($relevantData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                            . "\n\nAnweisung: {$instruction}",
                    ],
                ];

                $aiResult = $openAi->chat($messages, 'gpt-5.4-mini', [
                    'tools' => false,
                    'max_tokens' => $maxTokens,
                ]);

                $templateData[$sectionKey] = $aiResult['content'] ?? '';
            } catch (\Throwable $e) {
                Log::warning("[ReportGenerator] AI-Section {$sectionKey} fehlgeschlagen", [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
                $templateData[$sectionKey] = '*AI-Abschnitt konnte nicht generiert werden.*';
            }
        }

        // Phase 3: Blade-Template rendern
        $content = Blade::render($type->template, $templateData);

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
                'engine' => 'template',
                'tool_calls' => $toolCallNames,
                'ai_sections' => collect($type->ai_sections ?? [])->pluck('key')->toArray(),
                'model' => 'gpt-5.4-mini',
                'entity_context' => [
                    'entity_ids' => $entityContext['entity_ids'],
                    'linked_types' => array_keys($entityContext['linked']),
                ],
            ],
        ]);

        return $report->refresh();
    }

    /**
     * Ersetzt Platzhalter in Tool-Parametern (rekursiv für verschachtelte Arrays).
     */
    protected function resolveParams(array $params, OrganizationEntity $entity, array $period, array $entityContext = []): array
    {
        $replacements = [
            '{{entity.id}}' => $entity->id,
            '{{entity.code}}' => $entity->code,
            '{{entity.name}}' => $entity->name,
            '{{entity.team_id}}' => $entity->team_id,
            '{{period.from}}' => $period['from']->toDateString(),
            '{{period.to}}' => $period['to']->toDateString(),
            '{{period.from_iso}}' => $period['from']->toIso8601String(),
            '{{period.to_iso}}' => $period['to']->toIso8601String(),
            '{{entity_context.entity_ids}}' => $entityContext['entity_ids'] ?? [],
            '{{entity_context.project_ids}}' => $entityContext['linked']['project'] ?? [],
            '{{entity_context.ticket_ids}}' => $entityContext['linked']['helpdesk_ticket'] ?? [],
        ];

        return $this->resolveParamsRecursive($params, $replacements);
    }

    /**
     * Rekursive Platzhalter-Auflösung für verschachtelte Strukturen.
     */
    protected function resolveParamsRecursive(array $params, array $replacements): array
    {
        $resolved = [];
        foreach ($params as $key => $value) {
            if (is_string($value) && isset($replacements[$value])) {
                $resolved[$key] = $replacements[$value];
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveParamsRecursive($value, $replacements);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Löst den Entity-Kontext auf: Entity + alle Kinder rekursiv + verknüpfte Objekte.
     */
    protected function resolveEntityContext(OrganizationEntity $entity): array
    {
        // Entity mit rekursiven Kindern laden
        $entity->load('allChildren');

        // Alle Entity-IDs sammeln (self + descendants)
        $allEntityIds = $this->collectEntityIds($entity);
        $allEntityNames = $this->collectEntityNames($entity);

        // Alle EntityLinks für diese IDs laden
        $links = OrganizationEntityLink::whereIn('entity_id', $allEntityIds)
            ->get();

        // Nach linkable_type gruppiert → IDs extrahieren
        $linked = [];
        foreach ($links as $link) {
            $type = $link->linkable_type;
            if (!isset($linked[$type])) {
                $linked[$type] = [];
            }
            if (!in_array($link->linkable_id, $linked[$type])) {
                $linked[$type][] = $link->linkable_id;
            }
        }

        // Links nach Entity gruppiert (für detaillierte Aufschlüsselung)
        $linksByEntity = [];
        foreach ($links as $link) {
            $entityId = $link->entity_id;
            $type = $link->linkable_type;
            if (!isset($linksByEntity[$entityId])) {
                $linksByEntity[$entityId] = [];
            }
            if (!isset($linksByEntity[$entityId][$type])) {
                $linksByEntity[$entityId][$type] = [];
            }
            if (!in_array($link->linkable_id, $linksByEntity[$entityId][$type])) {
                $linksByEntity[$entityId][$type][] = $link->linkable_id;
            }
        }

        return [
            'entity_ids' => $allEntityIds,
            'entity_names' => $allEntityNames,
            'linked' => $linked,
            'links_by_entity' => $linksByEntity,
        ];
    }

    /**
     * Sammelt alle Entity-IDs rekursiv (self + descendants).
     */
    protected function collectEntityIds(OrganizationEntity $entity): array
    {
        $ids = [$entity->id];

        foreach ($entity->allChildren ?? [] as $child) {
            $ids = array_merge($ids, $this->collectEntityIds($child));
        }

        return $ids;
    }

    /**
     * Sammelt alle Entity-Namen rekursiv (self + descendants).
     */
    protected function collectEntityNames(OrganizationEntity $entity): array
    {
        $names = [$entity->name];

        foreach ($entity->allChildren ?? [] as $child) {
            $names = array_merge($names, $this->collectEntityNames($child));
        }

        return $names;
    }

    /**
     * Bestehender AI-Loop-Modus (backward-kompatibel).
     */
    protected function generateFromAiLoop(
        OrganizationReport $report,
        OrganizationReportType $type,
        OrganizationEntity $entity,
        string $outputChannel,
        \Carbon\Carbon $now,
    ): OrganizationReport {
        $systemPrompt = $this->buildSystemPrompt($type, $entity, $outputChannel);

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => "Erstelle jetzt den Bericht \"{$type->name}\" für die Entity \"{$entity->name}\". "
                    . "Schritt 1: Lade die benötigten Module-Tools via tools.GET(module=\"...\") für jedes Modul. "
                    . "Schritt 2: Hole alle relevanten Daten über die geladenen Tools. "
                    . "Schritt 3: Gib den fertigen Bericht als Markdown-Text aus (keine Tool-Calls mehr, nur Text).",
            ],
        ];

        $context = ToolContext::fromAuth();

        $runner = AiToolLoopRunner::make();
        $result = $runner->run($messages, 'gpt-5.4-mini', $context, [
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
                'engine' => 'ai_loop',
                'iterations' => $result['iterations'] ?? null,
                'tool_calls' => $result['all_tool_call_names'] ?? [],
                'model' => 'gpt-5.4-mini',
            ],
        ]);

        return $report->refresh();
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
- Nachdem du alle Daten via Tools geholt hast, MUSST du den fertigen Bericht als Markdown-Text ausgeben.
- Der Bericht muss als deine letzte Nachricht im Chat erscheinen — als normaler Text, NICHT als Tool-Call.
- Halte dich exakt an die vorgegebene Hülle/Gliederung.
- Nutze nur Daten, die du über die Tools abgerufen hast.
- Wenn für einen Abschnitt keine Daten vorliegen, schreibe "Keine Daten verfügbar."
- Deine finale Antwort MUSS der vollständige Bericht sein.
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
