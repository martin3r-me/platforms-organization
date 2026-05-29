<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Services\AiToolLoopRunner;
use Platform\Organization\Models\OrganizationInferenceRun;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
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

        's4' => 'Du bist ein diagnostischer Analyst für Zukunftsfähigkeit (VSM System 4). Du bewertest: Gibt es Entwicklungspfade? Werden Chancen und Risiken erkannt? Passt die aktuelle Aufstellung zur Zukunft? Fokus auf: Trends, strategische Positionierung, Innovationsfähigkeit.',

        's5' => 'Du bist ein diagnostischer Analyst für normative Kohärenz (VSM System 5). Du prüfst: Handelt die Organisation im Einklang mit ihren Werten und Zielen? Gibt es Widersprüche zwischen Strategie und Handeln? Fokus auf: Identität, Werte, Kohärenz.',
    ];

    /**
     * Execute a single inference prompt: assemble context, run LLM, process tool calls.
     */
    public function executePrompt(
        OrganizationSignalInferencePrompt $prompt,
        int $teamId,
        OrganizationInferenceRun $run
    ): array {
        $stats = [
            'signals_created' => 0,
            'inquiries_created' => 0,
            'memory_updates' => 0,
            'do_nothing_count' => 0,
            'entities_analyzed' => 0,
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
            $stats['entities_analyzed'] = count($evaluation['entities'] ?? []);

            // 2. Build system prompt based on VSM type
            $systemPrompt = $this->buildSystemPrompt($prompt);

            // 3. Build user message with evaluation context
            $userMessage = $this->buildUserMessage($prompt, $evaluation);

            // 4. Define available action tools
            $actionTools = $this->getActionToolNames($prompt->vsm_system);

            // 5. Get all read tools + action tools for preloading
            $preloadTools = array_merge(
                $this->getReadToolNames(),
                $actionTools
            );

            // 6. Run the LLM via AiToolLoopRunner
            $runner = AiToolLoopRunner::make();

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ];

            $result = $runner->run($messages, 'gpt-4o', $context, [
                'max_iterations' => 20,
                'max_output_tokens' => 4000,
                'skip_discovery_tools' => true,
                'preload_tools' => $preloadTools,
                'include_web_search' => false,
                'reasoning' => ['effort' => 'high'],
                'on_tool_result' => function (string $toolName, array $args, array $result) use (&$stats) {
                    // Track what the LLM does
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
                    }
                },
            ]);

            // Update run with LLM model and token usage
            $run->update([
                'llm_model' => 'gpt-4o',
                'token_usage' => $result['token_usage'] ?? null,
            ]);

            // Update last_evaluated_at
            $prompt->update(['last_evaluated_at' => now()]);

        } catch (\Throwable $e) {
            Log::error('[InferencePromptService] Prompt execution failed', [
                'prompt_id' => $prompt->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
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
            default => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()], // weekly
        };

        // Load signals for the period
        $signals = OrganizationSignal::forTeam($teamId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->with(['entity:id,name', 'inferencePrompt:id,name,vsm_system'])
            ->latest()
            ->limit(100)
            ->get();

        $algedonicSignals = $signals->where('severity', 'algedonic');

        // Build synthesis prompt
        $systemPrompt = 'Du bist ein strategischer Analyst der einen Synthese-Report für die Organisationsleitung erstellt. '
            . 'Dein Report verdichtet alle diagnostischen Erkenntnisse einer Periode in eine strukturierte Zusammenfassung. '
            . 'Format: Markdown. Sprache: Deutsch. '
            . 'Abschnitte: 1) Executive Summary, 2) Algedonic Signals (existenzielle Bedrohungen, separat hervorgehoben), '
            . '3) S3-Steuerungsbefunde, 4) S3*-Audit-Befunde, 5) S2-Cross-Entity-Muster, '
            . '6) Empfehlungen, 7) Offene Inquiries.';

        $signalSummary = $signals->map(fn ($s) => [
            'entity' => $s->entity?->name,
            'severity' => $s->severity,
            'message' => $s->message,
            'status' => $s->status,
            'vsm' => $s->inferencePrompt?->vsm_system,
            'date' => $s->created_at?->format('Y-m-d'),
        ])->toArray();

        $userMessage = "Erstelle einen {$reportType} Synthese-Report für den Zeitraum {$periodStart->format('d.m.Y')} bis {$periodEnd->format('d.m.Y')}.\n\n"
            . "Signale der Periode (" . count($signalSummary) . "):\n"
            . json_encode($signalSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $runner = AiToolLoopRunner::make();

        $result = $runner->run(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'gpt-4o',
            $context,
            [
                'max_iterations' => 5,
                'max_output_tokens' => 8000,
                'skip_discovery_tools' => true,
                'include_web_search' => false,
                'reasoning' => ['effort' => 'high'],
            ]
        );

        $title = ucfirst($reportType) . '-Report ' . $periodStart->format('d.m.Y') . ' – ' . $periodEnd->format('d.m.Y');

        // Save synthesis report
        $report = OrganizationSynthesisReport::create([
            'team_id' => $teamId,
            'inference_run_id' => $run->id,
            'report_type' => $reportType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'title' => $title,
            'content' => $result['assistant'] ?? '',
            'signals_included' => $signals->pluck('id')->toArray(),
            'algedonic_signals' => $algedonicSignals->pluck('id')->toArray(),
            'status' => 'draft',
        ]);

        return [
            'report_id' => $report->id,
            'title' => $title,
        ];
    }

    protected function buildSystemPrompt(OrganizationSignalInferencePrompt $prompt): string
    {
        $vsmPrompt = self::VSM_SYSTEM_PROMPTS[$prompt->vsm_system] ?? self::VSM_SYSTEM_PROMPTS['s3'];

        $baseInstructions = <<<PROMPT
{$vsmPrompt}

## Allgemeine Regeln

Du bist ein autonomer diagnostischer Analyst. Du hast Zugang zu allen Organisationsdaten über die verfügbaren Tools. Du kannst Entities nachschlagen, Beziehungen traversieren, Zeiterfassungen prüfen, Rollen und Skills abfragen.

### Verfügbare Aktionen

Nutze die folgenden Tools um deine Diagnose zu dokumentieren:

1. **organization.signal_inference.create_signal** — Erzeuge ein Signal wenn du ein Problem erkennst. Wähle die passende Severity:
   - `info`: Beobachtung, kein Handlungsbedarf
   - `warning`: Aufmerksamkeit erforderlich
   - `critical`: Handlungsbedarf
   - `algedonic`: Existenzielle Bedrohung — kurzschließt an S5

2. **organization.memory.POST** — Speichere eine Erkenntnis als Memory-Entry (Baseline, Entity-Profile, Suppression, etc.)

3. **organization.inference.do_nothing** — Explizit: Alles in Ordnung. Wird protokolliert. Nutze dies wenn die Lage unauffällig ist.

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

### do_nothing ist wertvoll

Wenn die Lage in Ordnung ist, rufe `organization.inference.do_nothing` auf mit einer kurzen Begründung. Das ist kein Fehler — es ist eine aktive diagnostische Entscheidung.
PROMPT;

        return $baseInstructions;
    }

    protected function buildUserMessage(OrganizationSignalInferencePrompt $prompt, array $evaluation): string
    {
        $entityCount = count($evaluation['entities'] ?? []);
        $existingSignals = count($evaluation['existing_signals'] ?? []);

        $message = "## Diagnostische Frage\n\n{$prompt->prompt_template}\n\n";
        $message .= "## Kontext\n\n";
        $message .= "- VSM-System: {$prompt->vsm_system}\n";
        $message .= "- Dimension: " . ($prompt->dimension ?: 'alle') . "\n";
        $message .= "- Entities im Scope: {$entityCount}\n";
        $message .= "- Offene Signale: {$existingSignals}\n";
        $message .= "- Inference-Prompt-ID: {$prompt->id}\n\n";

        // Entity data (truncated for context window)
        $entities = $evaluation['entities'] ?? [];
        if (! empty($entities)) {
            $message .= "## Entity-Daten\n\n";
            $message .= json_encode($entities, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        }

        // Communication summary
        $comms = $evaluation['communication_summary'] ?? [];
        if (! empty($comms)) {
            $message .= "## Kommunikations-Zusammenfassung\n\n";
            $message .= json_encode($comms, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        }

        // Existing signals
        $signals = $evaluation['existing_signals'] ?? [];
        if (! empty($signals)) {
            $message .= "## Bestehende offene Signale\n\n";
            $message .= json_encode($signals, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        }

        // Memory context
        $memory = $evaluation['memory_context'] ?? [];
        if (! empty($memory)) {
            $message .= "## Memory-Kontext (gelerntes Wissen)\n\n";
            $message .= json_encode($memory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        }

        $message .= "## Aufgabe\n\n";
        $message .= "Analysiere die Daten im Kontext der diagnostischen Frage. ";
        $message .= "Nutze die verfügbaren Tools um deine Erkenntnisse zu dokumentieren. ";
        $message .= "Du kannst bei Bedarf zusätzliche Daten über die Organization-Read-Tools laden.";

        return $message;
    }

    protected function getActionToolNames(string $vsmSystem): array
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

        return $tools;
    }

    protected function getReadToolNames(): array
    {
        return [
            'organization.entities.GET',
            'organization.entity_relationships.GET',
            'organization.interlinks.GET',
            'organization.cost_centers.GET',
            'organization.time_entries.GET',
            'organization.time_entries.summarize',
            'organization.job_profiles.GET',
            'organization.role_assignments.GET',
            'organization.skills.GET',
            'organization.person_skills.GET',
            'organization.perspectives.GET',
            'organization.vsm_functions.GET',
            'organization.dimension_values.GET',
            'organization.reports.GET',
            'organization.signals.GET',
            'organization.memory.GET',
            'organization.entity_movement.GET',
        ];
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
            // Fallback: create a minimal context
            $user = User::first();
        }

        return ToolContext::create($user, $team);
    }
}
