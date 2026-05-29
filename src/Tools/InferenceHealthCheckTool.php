<?php

namespace Platform\Organization\Tools;

use Illuminate\Support\Facades\Http;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\ToolRegistry;

class InferenceHealthCheckTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.inference.health';
    }

    public function getDescription(): string
    {
        return 'GET /organization/inference/health - Prüft ob der ClaudeToolLoopRunner korrekt konfiguriert ist: API Key, Model, ToolRegistry, Test-Call.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'test_api' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, wird ein minimaler Test-Call an die Anthropic API gemacht. Default: false.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $checks = [];

        // 1. Config check
        $apiKey = config('ai.anthropic.api_key', '');
        if (empty($apiKey)) {
            $apiKey = env('ANTHROPIC_API_KEY', '');
        }
        $keySet = ! empty($apiKey);
        $keyPreview = $keySet ? substr($apiKey, 0, 12) . '...' . substr($apiKey, -4) : null;

        $checks['api_key'] = [
            'configured' => $keySet,
            'preview' => $keyPreview,
            'source' => $keySet ? (config('ai.anthropic.api_key') ? 'config(ai.anthropic.api_key)' : 'env(ANTHROPIC_API_KEY)') : 'MISSING',
        ];

        // 2. Model config
        $model = config('ai.anthropic.inference_model', 'claude-sonnet-4-20250514');
        $checks['model'] = $model;

        // 3. Config merge check
        $checks['config_loaded'] = config('ai.anthropic') !== null;

        // 4. ToolRegistry check
        try {
            $registry = resolve(ToolRegistry::class);
            $allTools = $registry->names();
            $orgTools = array_filter($allTools, fn ($n) => str_starts_with($n, 'organization.'));

            $checks['tool_registry'] = [
                'total_tools' => count($allTools),
                'organization_tools' => count($orgTools),
                'key_tools_present' => [
                    'create_signal' => $registry->has('organization.signal_inference.create_signal'),
                    'memory_POST' => $registry->has('organization.memory.POST'),
                    'do_nothing' => $registry->has('organization.inference.do_nothing'),
                    'entities_GET' => $registry->has('organization.entities.GET'),
                ],
            ];
        } catch (\Throwable $e) {
            $checks['tool_registry'] = ['error' => $e->getMessage()];
        }

        // 5. ClaudeToolLoopRunner resolvable
        try {
            $runner = resolve(\Platform\Core\Services\ClaudeToolLoopRunner::class);
            $checks['runner_resolvable'] = true;
        } catch (\Throwable $e) {
            $checks['runner_resolvable'] = false;
            $checks['runner_error'] = $e->getMessage();
        }

        // 6. Optional: Test API call
        $testApi = (bool) ($arguments['test_api'] ?? false);
        if ($testApi && $keySet) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => '2023-06-01',
                        'content-type' => 'application/json',
                    ])
                    ->post('https://api.anthropic.com/v1/messages', [
                        'model' => $model,
                        'max_tokens' => 50,
                        'messages' => [
                            ['role' => 'user', 'content' => 'Antworte nur mit: OK'],
                        ],
                    ]);

                if ($response->successful()) {
                    $body = $response->json();
                    $text = $body['content'][0]['text'] ?? '';
                    $checks['api_test'] = [
                        'success' => true,
                        'response' => $text,
                        'model' => $body['model'] ?? null,
                        'usage' => $body['usage'] ?? null,
                    ];
                } else {
                    $body = $response->json();
                    $checks['api_test'] = [
                        'success' => false,
                        'status' => $response->status(),
                        'error_type' => $body['error']['type'] ?? 'unknown',
                        'error_message' => $body['error']['message'] ?? $response->body(),
                    ];
                }
            } catch (\Throwable $e) {
                $checks['api_test'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Overall status
        $healthy = $keySet
            && ($checks['config_loaded'] ?? false)
            && ($checks['runner_resolvable'] ?? false)
            && (! $testApi || ($checks['api_test']['success'] ?? false));

        return ToolResult::success([
            'healthy' => $healthy,
            'checks' => $checks,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'inference', 'debug', 'health'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
