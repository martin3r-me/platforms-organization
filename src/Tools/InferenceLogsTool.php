<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class InferenceLogsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.inference.logs';
    }

    public function getDescription(): string
    {
        return 'GET /organization/inference/logs - Liest die letzten Inference-relevanten Log-Einträge aus der Laravel Log-Datei.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lines' => [
                    'type' => 'integer',
                    'description' => 'Anzahl der letzten Zeilen aus dem Log. Default: 200.',
                ],
                'filter' => [
                    'type' => 'string',
                    'description' => 'Optional: Zusätzlicher Filter-String (z.B. "error", "failed", "ANTHROPIC").',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $linesToRead = min((int) ($arguments['lines'] ?? 200), 1000);
        $filter = $arguments['filter'] ?? null;

        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            return ToolResult::error('LOG_NOT_FOUND', 'Laravel log file not found.');
        }

        // Read last N lines efficiently
        $lines = $this->tailFile($logPath, $linesToRead);

        // Filter for inference-related entries
        $inferenceKeywords = [
            'InferencePromptService',
            'ClaudeToolLoop',
            'InferenceWorkerJob',
            'ANTHROPIC',
            'anthropic',
            'ClaudeToolLoopRunner',
            'inference_runs',
            'inference.do_nothing',
            'inference.health',
        ];

        $relevantEntries = [];
        $currentEntry = '';

        foreach ($lines as $line) {
            // New log entry starts with [YYYY-MM-DD
            if (preg_match('/^\[\d{4}-\d{2}-\d{2}/', $line)) {
                // Check previous entry
                if ($currentEntry !== '') {
                    if ($this->matchesKeywords($currentEntry, $inferenceKeywords, $filter)) {
                        $relevantEntries[] = trim($currentEntry);
                    }
                }
                $currentEntry = $line;
            } else {
                $currentEntry .= "\n" . $line;
            }
        }

        // Check last entry
        if ($currentEntry !== '' && $this->matchesKeywords($currentEntry, $inferenceKeywords, $filter)) {
            $relevantEntries[] = trim($currentEntry);
        }

        // Keep last 50 relevant entries max
        $relevantEntries = array_slice($relevantEntries, -50);

        return ToolResult::success([
            'entries' => $relevantEntries,
            'count' => count($relevantEntries),
            'log_file' => $logPath,
            'total_lines_scanned' => count($lines),
        ]);
    }

    protected function matchesKeywords(string $text, array $keywords, ?string $extraFilter): bool
    {
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                if ($extraFilter === null || stripos($text, $extraFilter) !== false) {
                    return true;
                }
                return true;
            }
        }

        return false;
    }

    protected function tailFile(string $path, int $lines): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);

        $result = [];
        while (! $file->eof()) {
            $line = $file->fgets();
            if ($line !== false) {
                $result[] = rtrim($line);
            }
        }

        return $result;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'inference', 'debug', 'logs'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
