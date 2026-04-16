<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Platform\Organization\Enums\ChainMemberRole;
use Platform\Organization\Enums\ProcessChainType;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessChain;
use Platform\Organization\Models\OrganizationProcessChainMember;
use Platform\Organization\Models\OrganizationProcessOutput;
use Platform\Organization\Models\OrganizationProcessTrigger;

class ProcessChainDetector
{
    public const MIN_CHAIN_SIZE = 2;
    public const MAX_SUB_PROCESS_DEPTH = 5;

    /**
     * Detects process chains for a team by analysing trigger/output links between processes.
     * Persists new ad_hoc chains unless $dryRun is true.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function detectChainsForTeam(int $teamId, bool $dryRun = false): Collection
    {
        $graph = $this->buildGraph($teamId);
        if (empty($graph['nodes'])) {
            return collect();
        }

        $components = $this->findConnectedComponents($graph['nodes'], $graph['edges']);
        $cycleNodes = $this->detectCycles($graph['nodes'], $graph['edges']);

        $results = collect();
        foreach ($components as $componentNodes) {
            if (count($componentNodes) < self::MIN_CHAIN_SIZE) {
                continue;
            }

            $signature = $this->signatureFor($componentNodes);
            $hasCycle = ! empty(array_intersect($componentNodes, $cycleNodes));

            $ordered = $this->orderByTopology($componentNodes, $graph['edges']);

            $existing = $dryRun
                ? null
                : OrganizationProcessChain::where('team_id', $teamId)
                    ->where('is_auto_detected', true)
                    ->whereJsonContains('metadata->signature', $signature)
                    ->first();

            if ($dryRun) {
                $results->push([
                    'id'          => null,
                    'uuid'        => null,
                    'name'        => $this->suggestName($teamId, $ordered),
                    'signature'   => $signature,
                    'process_ids' => $ordered,
                    'has_cycle'   => $hasCycle,
                    'is_new'      => true,
                    'chain_type'  => ProcessChainType::AdHoc->value,
                ]);
                continue;
            }

            if ($existing) {
                // Update members in place (idempotent)
                $this->syncMembers($existing, $ordered);
                $results->push([
                    'id'          => $existing->id,
                    'uuid'        => $existing->uuid,
                    'name'        => $existing->name,
                    'signature'   => $signature,
                    'process_ids' => $ordered,
                    'has_cycle'   => $hasCycle,
                    'is_new'      => false,
                    'chain_type'  => $existing->chain_type instanceof \BackedEnum ? $existing->chain_type->value : $existing->chain_type,
                ]);
                continue;
            }

            $chain = OrganizationProcessChain::create([
                'team_id'          => $teamId,
                'name'             => $this->suggestName($teamId, $ordered),
                'chain_type'       => ProcessChainType::AdHoc->value,
                'is_active'        => true,
                'is_auto_detected' => true,
                'metadata'         => [
                    'signature' => $signature,
                    'has_cycle' => $hasCycle,
                    'detected_at' => now()->toIso8601String(),
                ],
            ]);

            $this->syncMembers($chain, $ordered);

            $results->push([
                'id'          => $chain->id,
                'uuid'        => $chain->uuid,
                'name'        => $chain->name,
                'signature'   => $signature,
                'process_ids' => $ordered,
                'has_cycle'   => $hasCycle,
                'is_new'      => true,
                'chain_type'  => ProcessChainType::AdHoc->value,
            ]);
        }

        return $results;
    }

    /**
     * Promotes an ad_hoc chain to a value_stream / end_to_end chain.
     */
    public function promoteToChain(
        OrganizationProcessChain $chain,
        ProcessChainType $type,
        ?string $name = null
    ): OrganizationProcessChain {
        $chain->chain_type = $type->value;
        $chain->is_auto_detected = false;
        if ($name !== null && $name !== '') {
            $chain->name = $name;
        }
        $chain->save();
        return $chain;
    }

    /**
     * Connected Components via Union-Find (undirected).
     *
     * @param  array<int, int>                  $nodes
     * @param  array<int, array{from:int,to:int}> $edges
     * @return array<int, array<int, int>>
     */
    public function findConnectedComponents(array $nodes, array $edges): array
    {
        $parent = [];
        foreach ($nodes as $n) {
            $parent[$n] = $n;
        }

        $find = function (int $x) use (&$parent, &$find): int {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }
            return $x;
        };
        $union = function (int $a, int $b) use (&$parent, $find) {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$ra] = $rb;
            }
        };

        foreach ($edges as $e) {
            if (isset($parent[$e['from']]) && isset($parent[$e['to']])) {
                $union($e['from'], $e['to']);
            }
        }

        $groups = [];
        foreach ($nodes as $n) {
            $root = $find($n);
            $groups[$root][] = $n;
        }
        return array_values($groups);
    }

    /**
     * Returns all nodes that are part of a strongly-connected component with > 1 member (cycle).
     *
     * @param  array<int, int>                  $nodes
     * @param  array<int, array{from:int,to:int}> $edges
     * @return array<int, int>
     */
    public function detectCycles(array $nodes, array $edges): array
    {
        $adj = [];
        foreach ($nodes as $n) {
            $adj[$n] = [];
        }
        foreach ($edges as $e) {
            if (isset($adj[$e['from']])) {
                $adj[$e['from']][] = $e['to'];
            }
        }

        $index = 0;
        $stack = [];
        $onStack = [];
        $indices = [];
        $lowlinks = [];
        $cycleNodes = [];

        $strongconnect = function (int $v) use (&$strongconnect, &$index, &$stack, &$onStack, &$indices, &$lowlinks, &$cycleNodes, $adj) {
            $indices[$v] = $index;
            $lowlinks[$v] = $index;
            $index++;
            $stack[] = $v;
            $onStack[$v] = true;

            foreach ($adj[$v] ?? [] as $w) {
                if (! isset($indices[$w])) {
                    $strongconnect($w);
                    $lowlinks[$v] = min($lowlinks[$v], $lowlinks[$w]);
                } elseif (! empty($onStack[$w])) {
                    $lowlinks[$v] = min($lowlinks[$v], $indices[$w]);
                }
            }

            if ($lowlinks[$v] === $indices[$v]) {
                $component = [];
                do {
                    $w = array_pop($stack);
                    $onStack[$w] = false;
                    $component[] = $w;
                } while ($w !== $v);

                if (count($component) > 1) {
                    foreach ($component as $n) {
                        $cycleNodes[$n] = true;
                    }
                } else {
                    // self-loop = cycle too
                    if (in_array($v, $adj[$v] ?? [], true)) {
                        $cycleNodes[$v] = true;
                    }
                }
            }
        };

        foreach ($nodes as $n) {
            if (! isset($indices[$n])) {
                $strongconnect($n);
            }
        }

        return array_keys($cycleNodes);
    }

    // ───────────────────────────── helpers ─────────────────────────────

    /**
     * Build process-level directed graph: edges from trigger.source_process_id → process_id
     * and from process_id → output.target_process_id.
     *
     * @return array{nodes: int[], edges: array<int, array{from:int,to:int}>}
     */
    protected function buildGraph(int $teamId): array
    {
        $processIds = OrganizationProcess::where('team_id', $teamId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $edges = [];

        OrganizationProcessTrigger::where('team_id', $teamId)
            ->whereNotNull('source_process_id')
            ->get(['process_id', 'source_process_id'])
            ->each(function ($t) use (&$edges) {
                $from = (int) $t->source_process_id;
                $to = (int) $t->process_id;
                if ($from > 0 && $to > 0 && $from !== $to) {
                    $edges[] = ['from' => $from, 'to' => $to];
                } elseif ($from > 0 && $from === $to) {
                    $edges[] = ['from' => $from, 'to' => $to]; // self-loop
                }
            });

        OrganizationProcessOutput::where('team_id', $teamId)
            ->whereNotNull('target_process_id')
            ->get(['process_id', 'target_process_id'])
            ->each(function ($o) use (&$edges) {
                $from = (int) $o->process_id;
                $to = (int) $o->target_process_id;
                if ($from > 0 && $to > 0) {
                    $edges[] = ['from' => $from, 'to' => $to];
                }
            });

        return [
            'nodes' => $processIds,
            'edges' => $edges,
        ];
    }

    /**
     * Return process IDs ordered by a best-effort topological sort (Kahn's algorithm).
     * Nodes in cycles are appended in their original order.
     *
     * @param  array<int, int>                  $componentNodes
     * @param  array<int, array{from:int,to:int}> $edges
     * @return array<int, int>
     */
    protected function orderByTopology(array $componentNodes, array $edges): array
    {
        $set = array_flip($componentNodes);
        $inDegree = [];
        $adj = [];
        foreach ($componentNodes as $n) {
            $inDegree[$n] = 0;
            $adj[$n] = [];
        }
        foreach ($edges as $e) {
            if (isset($set[$e['from']]) && isset($set[$e['to']]) && $e['from'] !== $e['to']) {
                $adj[$e['from']][] = $e['to'];
                $inDegree[$e['to']]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $n => $d) {
            if ($d === 0) {
                $queue[] = $n;
            }
        }

        $ordered = [];
        while (! empty($queue)) {
            sort($queue);
            $n = array_shift($queue);
            $ordered[] = $n;
            foreach ($adj[$n] as $m) {
                $inDegree[$m]--;
                if ($inDegree[$m] === 0) {
                    $queue[] = $m;
                }
            }
        }

        // Append any remaining (cycle) nodes
        $remaining = array_diff($componentNodes, $ordered);
        foreach ($remaining as $n) {
            $ordered[] = $n;
        }

        return array_values(array_unique($ordered));
    }

    /**
     * Idempotent signature (hash over sorted process IDs).
     *
     * @param array<int, int> $processIds
     */
    protected function signatureFor(array $processIds): string
    {
        $sorted = $processIds;
        sort($sorted);
        return hash('sha256', implode(',', $sorted));
    }

    /**
     * Suggests a chain name based on first/last process.
     *
     * @param array<int, int> $orderedProcessIds
     */
    protected function suggestName(int $teamId, array $orderedProcessIds): string
    {
        if (empty($orderedProcessIds)) {
            return 'Prozesskette (auto)';
        }
        $first = OrganizationProcess::where('team_id', $teamId)->find($orderedProcessIds[0]);
        $last = OrganizationProcess::where('team_id', $teamId)->find($orderedProcessIds[array_key_last($orderedProcessIds)]);

        if ($first && $last && $first->id !== $last->id) {
            return sprintf('Kette: %s → %s', $first->name, $last->name);
        }
        if ($first) {
            return sprintf('Kette um "%s"', $first->name);
        }
        return 'Prozesskette (auto)';
    }

    /**
     * Rewrites members of $chain to match $orderedProcessIds (idempotent).
     *
     * @param array<int, int> $orderedProcessIds
     */
    protected function syncMembers(OrganizationProcessChain $chain, array $orderedProcessIds): void
    {
        $existingByProcess = $chain->members()->get()->keyBy('process_id');
        $keepProcessIds = [];

        foreach ($orderedProcessIds as $i => $pid) {
            $role = ChainMemberRole::Middle->value;
            if ($i === 0) {
                $role = ChainMemberRole::Entry->value;
            } elseif ($i === count($orderedProcessIds) - 1) {
                $role = ChainMemberRole::Exit->value;
            }

            $position = $i + 1;
            $keepProcessIds[] = $pid;

            /** @var OrganizationProcessChainMember|null $member */
            $member = $existingByProcess->get($pid);
            if ($member) {
                if ((int) $member->position !== $position || (string) ($member->role instanceof \BackedEnum ? $member->role->value : $member->role) !== $role) {
                    $member->update([
                        'position' => $position,
                        'role'     => $role,
                    ]);
                }
            } else {
                OrganizationProcessChainMember::create([
                    'team_id'     => $chain->team_id,
                    'chain_id'    => $chain->id,
                    'process_id'  => $pid,
                    'position'    => $position,
                    'role'        => $role,
                    'is_required' => true,
                ]);
            }
        }

        // Soft-delete members that are no longer part of the detected chain
        $chain->members()
            ->whereNotIn('process_id', $keepProcessIds)
            ->get()
            ->each(fn ($m) => $m->delete());

        $chain->refresh();
        $chain->syncEndpointsFromMembers();
    }
}
