<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Services\ClaudeToolLoopRunner;
use Platform\Organization\Models\OrganizationInferenceRun;
use Platform\Organization\Models\OrganizationInferenceRunStep;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Models\OrganizationSynthesisPromptDefinition;
use Platform\Organization\Models\OrganizationSynthesisReport;
use Platform\Organization\Tools\EvaluateSignalInferenceTool;

class InferencePromptService
{
    /**
     * VSM-type specific system prompts that define the LLM's role/perspective.
     */
    protected const VSM_SYSTEM_PROMPTS = [
        's1' => 'Du bist ein diagnostischer Analyst für operative Lebensfähigkeit (VSM System 1). Du bewertest einzelne operative Einheiten: Funktionieren sie? Haben sie die Ressourcen die sie brauchen? Gibt es Blockaden oder Stillstand? Fokus auf: Aktivität, Fortschritt, Ressourcenverfügbarkeit.',

        's2' => 'Du bist ein diagnostischer Analyst für Koordination (VSM System 2). Du suchst nach Konflikten, Abhängigkeiten und widersprüchlichen Entwicklungen ZWISCHEN Einheiten. Cross-Entity-Muster sind dein Spezialgebiet. Fokus auf: Schnittstellen, Synchronisation, Widersprüche.',

        's3' => 'Du bist ein diagnostischer Analyst für Steuerung und Ressourcenallokation (VSM System 3). Du prüfst: Stimmt die Ressourcenverteilung? Werden Normen und Prioritäten eingehalten? Gibt es Mismatches zwischen Aufwand und Bedeutung? Fokus auf: Allokation, Compliance, Effizienz.',

        's3_star' => 'Du bist der Auditor (VSM System 3*). Du prüfst NICHT Inhalte, sondern ob Informationskanäle funktionieren. Schweigen ist dein primäres Signal. Wenn eine Entity keine Snapshots hat, keine Aktivität zeigt, keine Correspondence — das IST dein Befund. Du stellst KEINE Fragen an Menschen (ask_inquiry steht dir nicht zur Verfügung). Rohdaten sprechen für sich.',

        's4' => 'Du bist ein diagnostischer Analyst für Zukunftsfähigkeit (VSM System 4). Du bewertest: Gibt es Entwicklungspfade? Werden Chancen und Risiken erkannt? Passt die aktuelle Aufstellung zur Zukunft? Fokus auf: Trends, strategische Positionierung, Innovationsfähigkeit.

Wenn Umwelt-Daten (environment) im Kontext enthalten sind:
- Identifiziere Trends die strategische Chancen oder Bedrohungen darstellen
- Bewerte ob die Organisation auf erkannte Trends vorbereitet ist
- Verknüpfe externe Entwicklungen mit internen Entities und Capabilities
- Erzeuge Signale wenn externe Trends eine strategische Reaktion erfordern',

        's5' => 'Du bist ein diagnostischer Analyst für normative Kohärenz (VSM System 5). Du prüfst: Handelt die Organisation im Einklang mit ihren Werten und Zielen? Gibt es Widersprüche zwischen Strategie und Handeln? Fokus auf: Identität, Werte, Kohärenz.

Wenn Umwelt-Daten (environment) im Kontext enthalten sind:
- Prüfe ob regulatorische Änderungen das Wertesystem der Organisation tangieren
- Bewerte ob die Organisationsidentität zur sich ändernden Umwelt passt
- Identifiziere Spannungsfelder zwischen externen Anforderungen und interner Kultur',
    ];

    /**
     * Execute a single inference prompt: assemble context, run LLM, process tool calls.
     */
    public function executePrompt(
        OrganizationSignalInferencePrompt $prompt,
        int $teamId,
        OrganizationInferenceRun $run,
        ?array $entityFilter = null
    ): array {
        $stats = [
            'signals_created' => 0,
            'inquiries_created' => 0,
            'memory_updates' => 0,
            'do_nothing_count' => 0,
            'entities_analyzed' => 0,
            'environment_ratings' => 0,
        ];

        try {
            // 1. Build the context using the existing EvaluateSignalInferenceTool
            $evaluateTool = new EvaluateSignalInferenceTool();
            $context = $this->buildToolContext($teamId);

            $evalResult = $evaluateTool->execute([
                'team_id' => $teamId,
                'inference_prompt_id' => $prompt->id,
            ], $context);

            $evalData = $evalResult->toArray();

            if (empty($evalData['data']['evaluations'])) {
                return $stats;
            }

            $evaluation = $evalData['data']['evaluations'][0] ?? [];

            // Apply entity_filter if provided (from trigger)
            if ($entityFilter && ! empty($evaluation['entities'])) {
                $evaluation['entities'] = array_values(array_filter(
                    $evaluation['entities'],
                    fn ($e) => in_array($e['id'] ?? null, $entityFilter)
                ));
            }

            $stats['entities_analyzed'] = count($evaluation['entities'] ?? []);

            // 2. Build system prompt based on VSM type
            $systemPrompt = $this->buildSystemPrompt($prompt);

            // 3. Build user message with evaluation context
            $userMessage = $this->buildUserMessage($prompt, $evaluation);

            // 4. Action tools exposed directly (Claude calls them by name).
            //    All other tools (read, search, etc.) are available via
            //    discover_tools + execute_tool (MCP pattern, full ToolRegistry).
            $actionTools = $this->getActionToolNames($prompt->vsm_system, $prompt->data_sources ?? null);

            // 5. Run via ClaudeToolLoopRunner
            $runner = ClaudeToolLoopRunner::make();

            $stepIndex = 0;
            $promptId = $prompt->id;
            $runId = $run->id;

            $result = $runner->run(
                [
                    ['role' => 'user', 'content' => $userMessage],
                ],
                $context,
                [
                    'system' => $systemPrompt,
                    'model' => config('ai.anthropic.inference_model', 'claude-sonnet-4-6'),
                    'max_tokens' => 4096,
                    'max_iterations' => 20,
                    'tools' => $actionTools,
                    // include_meta_tools defaults to true → discover_tools + execute_tool
                    'temperature' => 0.3,
                    'on_tool_result' => function (string $toolName, array $args, array $result) use (&$stats, &$stepIndex, $runId, $promptId) {
                        // Step-Logging: jeder Tool-Call wird mit Argumenten und Result als Step persistiert.
                        try {
                            $resultOk = empty($result['error']) && empty($result['_error']);
                            $errorMsg = $result['error'] ?? ($result['_error'] ?? null);

                            OrganizationInferenceRunStep::create([
                                'inference_run_id' => $runId,
                                'inference_prompt_id' => $promptId,
                                'step_index' => $stepIndex++,
                                'step_type' => OrganizationInferenceRunStep::TYPE_TOOL_CALL,
                                'tool_name' => $toolName,
                                'arguments' => $this->truncateForStep($args),
                                'result' => $this->truncateForStep($result),
                                'result_ok' => $resultOk,
                                'error_message' => is_string($errorMsg) ? $errorMsg : null,
                                'occurred_at' => now(),
                            ]);
                        } catch (\Throwable $e) {
                            Log::warning('[InferencePromptService] Step logging failed', [
                                'tool_name' => $toolName,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        if ($toolName === 'organization.signal_inference.create_signal') {
                            if (! empty($result['data']['id']) && empty($result['data']['skipped'])) {
                                $stats['signals_created']++;
                            }
                        } elseif ($toolName === 'organization.memory.POST') {
                            $stats['memory_updates']++;
                        } elseif ($toolName === 'organization.inquiries.create') {
                            $stats['inquiries_created']++;
                        } elseif ($toolName === 'organization.inference.do_nothing') {
                            $stats['do_nothing_count']++;
                        } elseif ($toolName === 'organization.environment.rate_source') {
                            $stats['environment_ratings']++;
                        }
                    },
                ]
            );

            // Return token usage via stats for accumulation in worker
            $stats['token_usage'] = $result['token_usage'] ?? null;
            $stats['llm_model'] = $result['model'] ?? 'claude-sonnet-4-6';

            // Update last_evaluated_at + run_count, clear last_error
            $prompt->update([
                'last_evaluated_at' => now(),
                'last_error' => null,
                'run_count' => ($prompt->run_count ?? 0) + 1,
            ]);

            Log::info('[InferencePromptService] Prompt executed', [
                'prompt_id' => $prompt->id,
                'iterations' => $result['iterations'],
                'tool_calls' => count($result['tool_calls']),
                'stats' => $stats,
            ]);

        } catch (\Throwable $e) {
            // Persistiere Fehler am Prompt fuer Health-Status.
            try {
                $prompt->update([
                    'last_error' => substr($e->getMessage(), 0, 1000),
                    'last_evaluated_at' => now(),
                    'run_count' => ($prompt->run_count ?? 0) + 1,
                ]);
            } catch (\Throwable) {
                // ignore
            }

            Log::error('[InferencePromptService] Prompt execution failed', [
                'prompt_id' => $prompt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $stats;
    }

    /**
     * Komprimiert grosse Tool-Argumente/-Results fuer die Step-Tabelle:
     * Bei >8KB JSON wird nur ein Auszug gespeichert.
     */
    protected function truncateForStep(?array $data, int $maxBytes = 8192): ?array
    {
        if ($data === null) {
            return null;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) <= $maxBytes) {
            return $data;
        }

        return [
            '_truncated' => true,
            '_original_bytes' => strlen($json),
            '_excerpt' => substr($json, 0, $maxBytes),
        ];
    }

    /**
     * Generate a synthesis report for a team.
     */
    public function generateSynthesisReport(int $teamId, string $reportType, OrganizationInferenceRun $run): array
    {
        $context = $this->buildToolContext($teamId);

        // Determine period
        [$periodStart, $periodEnd] = match ($reportType) {
            'monthly' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'quarterly' => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            default => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
        };

        // Resolve definition: first active match in DB, otherwise hardcoded fallback
        $definition = OrganizationSynthesisPromptDefinition::forTeam($teamId)
            ->forReportType($reportType)
            ->active()
            ->orderBy('id')
            ->first();

        $usingFallback = ! $definition;

        if ($usingFallback) {
            Log::info('[InferencePromptService] No active synthesis prompt definition; using hardcoded fallback', [
                'team_id' => $teamId,
                'report_type' => $reportType,
            ]);
        }

        $maxSignals = $definition?->max_signals ?? 100;

        // Load signals for the period
        $signals = OrganizationSignal::forTeam($teamId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->with(['entity:id,name', 'inferencePrompt:id,name,vsm_system'])
            ->latest()
            ->limit($maxSignals)
            ->get();

        $algedonicSignals = $signals->where('severity', 'algedonic');

        $signalSummary = $signals->map(fn ($s) => [
            'entity' => $s->entity?->name,
            'severity' => $s->severity,
            'message' => $s->message,
            'status' => $s->status,
            'vsm' => $s->inferencePrompt?->vsm_system,
            'date' => $s->created_at?->format('Y-m-d'),
        ])->toArray();

        $systemPrompt = $definition?->system_prompt
            ?? OrganizationSynthesisPromptDefinition::DEFAULT_SYSTEM_PROMPT;

        $templateValues = [
            'report_type' => $reportType,
            'period_start' => $periodStart->format('d.m.Y'),
            'period_end' => $periodEnd->format('d.m.Y'),
            'signals_count' => count($signalSummary),
            'signals_json' => json_encode($signalSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ];

        if ($definition) {
            $userMessage = $definition->renderUserMessage($templateValues);
        } else {
            $userMessage = strtr(OrganizationSynthesisPromptDefinition::DEFAULT_USER_TEMPLATE, [
                '{report_type}' => $templateValues['report_type'],
                '{period_start}' => $templateValues['period_start'],
                '{period_end}' => $templateValues['period_end'],
                '{signals_count}' => (string) $templateValues['signals_count'],
                '{signals_json}' => $templateValues['signals_json'],
            ]);
        }

        $runner = ClaudeToolLoopRunner::make();

        $result = $runner->run(
            [
                ['role' => 'user', 'content' => $userMessage],
            ],
            $context,
            [
                'system' => $systemPrompt,
                'model' => $definition?->model ?: config('ai.anthropic.inference_model', 'claude-sonnet-4-6'),
                'max_tokens' => $definition?->max_tokens ?? 8192,
                'max_iterations' => 5,
                // meta-tools (discover + execute) are included by default
            ]
        );

        $title = ($definition?->name ? $definition->name . ' · ' : ucfirst($reportType) . '-Report ')
            . $periodStart->format('d.m.Y') . ' – ' . $periodEnd->format('d.m.Y');

        // Save synthesis report
        $report = OrganizationSynthesisReport::create([
            'team_id' => $teamId,
            'inference_run_id' => $run->id,
            'report_type' => $reportType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'title' => $title,
            'content' => $result['assistant_text'] ?? '',
            'signals_included' => $signals->pluck('id')->toArray(),
            'algedonic_signals' => $algedonicSignals->pluck('id')->toArray(),
            'status' => 'draft',
        ]);

        return [
            'report_id' => $report->id,
            'title' => $title,
            'definition_id' => $definition?->id,
            'using_fallback' => $usingFallback,
        ];
    }

    protected function buildSystemPrompt(OrganizationSignalInferencePrompt $prompt): string
    {
        $vsmPrompt = self::VSM_SYSTEM_PROMPTS[$prompt->vsm_system] ?? self::VSM_SYSTEM_PROMPTS['s3'];

        // Leitbild / SemanticLayer injizieren
        $leitbildBlock = '';
        try {
            $team = Team::find($prompt->team_id);
            if ($team) {
                $resolver = resolve(\Platform\Core\SemanticLayer\Services\SemanticLayerResolver::class);
                $resolved = $resolver->resolveFor($team, 'inference');
                if (!$resolved->isEmpty()) {
                    $leitbildBlock = "\n\n## Organisatorischer Bewertungsrahmen\n\n" . $resolved->rendered_block;
                }
            }
        } catch (\Throwable) {
            // SemanticLayer module may not be available
        }

        $baseInstructions = <<<PROMPT
{$vsmPrompt}
{$leitbildBlock}

## Allgemeine Regeln

Du bist ein autonomer diagnostischer Analyst. Du hast Zugang zu allen Organisationsdaten über die verfügbaren Tools. Du kannst Entities nachschlagen, Beziehungen traversieren, Zeiterfassungen prüfen, Rollen und Skills abfragen.

### Aktions-Tools (direkt aufrufbar)

Nutze die folgenden Tools um deine Diagnose zu dokumentieren:

1. **organization_signal_inference_create_signal** — Erzeuge ein Signal wenn du ein Problem erkennst. Wähle die passende Severity:
   - `info`: Beobachtung, kein Handlungsbedarf
   - `warning`: Aufmerksamkeit erforderlich
   - `critical`: Handlungsbedarf
   - `algedonic`: Existenzielle Bedrohung — kurzschließt an S5

2. **organization_memory_POST** — Speichere eine Erkenntnis als Memory-Entry (Baseline, Entity-Profile, Suppression, etc.)

3. **organization_inference_do_nothing** — Explizit: Alles in Ordnung. Wird protokolliert. Nutze dies wenn die Lage unauffällig ist.

### Daten-Tools (über execute_tool)

Für zusätzliche Daten nutze **execute_tool**:
- **Tool-Suche**: execute_tool(tool="tool_registry.SEARCH", arguments={"query": "entities"})
- **Tool ausführen**: execute_tool(tool="organization.entities.GET", arguments={"type": "project"})

Beispiele für Daten-Tools: organization.entities.GET, organization.signals.GET, organization.time_entries.GET, organization.entity_relationships.GET, organization.memory.GET, organization.entity_movement.GET u.v.m.

### Memory-Kontext beachten

Die `memory_context`-Sektion enthält gelerntes Wissen:
- **Suppressions** (confidence ≥ 0.7): Diese Muster wurden bewusst als irrelevant markiert. Erzeuge KEIN Signal für supprimierte Muster.
- **Baselines**: Gelernte Normalwerte. Abweichungen davon sind relevant.
- **Entity-Profile**: Angereicherte Beschreibungen. Berücksichtige den Kontext.

### Confidence-Gate für Inquiries

Bevor du eine Inquiry stellst, frage dich: Ist der Informationsgewinn die Beziehungskosten wert? Eine falsche Inquiry nervt. Lieber kein Signal als eine überflüssige Frage.

### Algedonic Signals

Nutze severity `algedonic` NUR bei echten existenziellen Bedrohungen:
- Mehrere kritische Signale in derselben Entity, alle unbearbeitet
- Schlüsselposition vakant + Entity ohne Fortschritt
- Kritische Schwellwerte dauerhaft unterschritten

### Umwelt-Quellen bewerten

Wenn ein Abschnitt "Umwelt-Kontext" in den Daten enthalten ist:

1. **Analysiere zuerst** — Wie beeinflussen die externen Entwicklungen die Organisation?
2. **Verknüpfe** — Welche Entities werden von externen Trends betroffen?
3. **Erzeuge Signale** — Wenn ein externer Trend Handlungsbedarf auslöst
4. **Bewerte jede Quelle** mit `organization_environment_rate_source`:
   - relevance_rating: Wie nützlich war die Quelle für deine Diagnose? (0.0-1.0)
   - cited_in_signal: Hast du ein Signal erzeugt das auf diesen Daten basiert?
   - topics_useful: Welche Themen waren diagnostisch wertvoll?
   - topics_noise: Welche Themen waren irrelevant?
   - reasoning: 1 Satz Begründung

Dies verbessert zukünftige Extraktionen und Priorisierung.

### do_nothing ist wertvoll

Wenn die Lage in Ordnung ist, rufe `organization_inference_do_nothing` auf mit einer kurzen Begründung. Das ist kein Fehler — es ist eine aktive diagnostische Entscheidung.
PROMPT;

        return $baseInstructions;
    }

    protected function buildUserMessage(OrganizationSignalInferencePrompt $prompt, array $evaluation): string
    {
        $entities = $evaluation['entities'] ?? [];
        $entityCount = count($entities);
        $existingSignals = count($evaluation['existing_signals'] ?? []);

        $message = "## Diagnostische Frage\n\n{$prompt->prompt_template}\n\n";
        $message .= "## Kontext\n\n";
        $message .= "- VSM-System: {$prompt->vsm_system}\n";
        $message .= "- Dimension: " . ($prompt->dimension ?: 'alle') . "\n";
        $message .= "- Entities im Scope: {$entityCount}\n";
        $message .= "- Offene Signale: {$existingSignals}\n";
        $message .= "- Inference-Prompt-ID: {$prompt->id}\n\n";

        // Entity data — compact format to stay within token limits
        if (! empty($entities)) {
            $message .= "## Entity-Daten\n\n";
            $message .= $this->formatEntitiesCompact($entities) . "\n\n";
        }

        // Environment context — dedicated section (before comms, more prominent)
        $comms = $evaluation['communication_summary'] ?? [];
        $envData = $comms['environment'] ?? [];
        if (! empty($envData)) {
            unset($comms['environment']);
            $message .= "## Umwelt-Kontext (externe Datenquellen)\n\n";
            foreach ($envData as $env) {
                $source = $env['source'] ?? '?';
                $cluster = $env['cluster'] ?? '?';
                $category = $env['category'] ?? '?';
                $message .= "### {$source} [{$cluster}/{$category}]\n";
                $message .= "Relevanz: " . ($env['relevance'] ?? '?') . " | Sentiment: " . ($env['sentiment'] ?? '?');
                if (($env['learned_relevance'] ?? 0.5) != 0.5) {
                    $message .= " | Gelernte Relevanz: {$env['learned_relevance']}";
                }
                $message .= "\n";
                $message .= ($env['summary'] ?? '') . "\n";
                if (! empty($env['topics'])) {
                    $message .= "Topics: " . implode(', ', $env['topics']) . "\n";
                }
                if (! empty($env['delta'])) {
                    $d = $env['delta'];
                    $message .= "Delta: Sentiment " . ($d['sentiment_change'] ?? '0') . ", Relevanz " . ($d['relevance_change'] ?? '0') . "\n";
                }
                $message .= "\n";
            }
        }

        // Communication summary — only if non-empty
        $comms = array_filter($comms);
        if (! empty($comms)) {
            $message .= "## Kommunikations-Zusammenfassung\n\n";
            $message .= json_encode($comms, JSON_UNESCAPED_UNICODE) . "\n\n";
        }

        // Existing signals — compact
        $signals = $evaluation['existing_signals'] ?? [];
        if (! empty($signals)) {
            $message .= "## Bestehende offene Signale ({$existingSignals})\n\n";
            foreach ($signals as $s) {
                $message .= "- [{$s['severity']}] Entity #{$s['entity_id']}: {$s['message']}\n";
            }
            $message .= "\n";
        }

        // Memory context — only non-empty sections
        $memory = $evaluation['memory_context'] ?? [];
        $memoryParts = [];
        foreach ($memory as $key => $entries) {
            if (! empty($entries)) {
                $memoryParts[$key] = $entries;
            }
        }
        if (! empty($memoryParts)) {
            $message .= "## Memory-Kontext (gelerntes Wissen)\n\n";
            $message .= json_encode($memoryParts, JSON_UNESCAPED_UNICODE) . "\n\n";
        }

        $message .= "## Aufgabe\n\n";
        $message .= "Analysiere die Daten im Kontext der diagnostischen Frage. ";
        $message .= "Nutze die verfügbaren Tools um deine Erkenntnisse zu dokumentieren. ";
        $message .= "Für Details zu einzelnen Entities nutze execute_tool(tool=\"organization.entities.GET\", arguments={\"id\": <id>}).";

        return $message;
    }

    /**
     * Format entities in a compact text format instead of verbose JSON.
     * Reduces token count by ~70% compared to JSON_PRETTY_PRINT.
     */
    protected function formatEntitiesCompact(array $entities): string
    {
        $lines = [];

        foreach ($entities as $entity) {
            $id = $entity['id'];
            $name = $entity['name'];
            $type = $entity['type'] ?? '?';
            $parent = $entity['parent'] ?? '-';
            $vsm = ! empty($entity['vsm_functions']) ? implode(',', $entity['vsm_functions']) : '-';

            // Snapshot: extract key metrics only (non-null, non-zero)
            $snapshotSummary = '-';
            if (! empty($entity['snapshot_latest'])) {
                $metrics = array_filter($entity['snapshot_latest'], fn ($v) => $v !== null && $v !== 0 && $v !== '');
                if (! empty($metrics)) {
                    $parts = [];
                    foreach ($metrics as $k => $v) {
                        $parts[] = "{$k}={$v}";
                    }
                    $snapshotSummary = implode(', ', array_slice($parts, 0, 8));
                    if (count($parts) > 8) {
                        $snapshotSummary .= ' (+' . (count($parts) - 8) . ')';
                    }
                }
            }

            // Movement: only non-zero deltas
            $movementSummary = '-';
            if (! empty($entity['movement_7d'])) {
                $deltas = array_filter($entity['movement_7d'], fn ($v) => $v !== null && $v !== 0 && $v !== 0.0);
                if (! empty($deltas)) {
                    $parts = [];
                    foreach ($deltas as $k => $v) {
                        $sign = $v > 0 ? '+' : '';
                        $parts[] = "{$k}:{$sign}{$v}";
                    }
                    $movementSummary = implode(', ', array_slice($parts, 0, 5));
                }
            }

            $lines[] = "#{$id} {$name} [{$type}] parent={$parent} vsm={$vsm} | snap: {$snapshotSummary} | mov7d: {$movementSummary}";
        }

        return implode("\n", $lines);
    }

    protected function getActionToolNames(string $vsmSystem, ?array $dataSources = null): array
    {
        $tools = [
            'organization.signal_inference.create_signal',
            'organization.memory.POST',
            'organization.inference.do_nothing',
        ];

        // S3* does NOT get ask_inquiry
        if ($vsmSystem !== 's3_star') {
            $tools[] = 'organization.inquiries.create';
        }

        // Include rate_source tool when environment data is in context
        if (in_array('environment', $dataSources ?? [])) {
            $tools[] = 'organization.environment.rate_source';
        }

        return $tools;
    }

    protected function buildToolContext(int $teamId): ToolContext
    {
        $team = Team::find($teamId);

        // Use the first admin user of the team for context
        $user = $team?->users()
            ->wherePivot('role', 'admin')
            ->first();

        if (! $user) {
            $user = $team?->users()->first();
        }

        if (! $user) {
            $user = User::first();
        }

        return ToolContext::create($user, $team);
    }
}
