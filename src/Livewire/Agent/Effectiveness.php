<?php

namespace Platform\Organization\Livewire\Agent;

use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Services\AgentEffectiveness;

/**
 * Wirksamkeits-Bilanz der Agenten: was sie in der Organisation bewirken — Ergebnisse, Anfragen an
 * Menschen, Qualität, Tempo, Einsatz und Kosten. Gegenstück zur Leitwarte (die zeigt, wie gesund die
 * Gehirne laufen). Zeitraum wählbar, dazu der Verlauf der Ergebnisse über acht Wochen.
 */
class Effectiveness extends Component
{
    /** week | last_week | 4w */
    public string $range = 'week';

    protected $queryString = ['range' => ['except' => 'week']];

    private const WEEKS = 8;

    /** @return array{0:Carbon,1:Carbon,2:string} */
    private function bounds(): array
    {
        return match ($this->range) {
            'last_week' => [now()->startOfWeek()->subWeek(), now()->startOfWeek()->subWeek()->endOfWeek(), 'Letzte Woche'],
            '4w' => [now()->startOfWeek()->subWeeks(3), now(), 'Letzte 4 Wochen'],
            default => [now()->startOfWeek(), now(), 'Diese Woche'],
        };
    }

    #[Computed]
    public function rows(): array
    {
        [$from, $to] = $this->bounds();

        return app(AgentEffectiveness::class)->period($from, $to);
    }

    #[Computed]
    public function trend(): array
    {
        return app(AgentEffectiveness::class)->weeklyDone(self::WEEKS);
    }

    public function setRange(string $r): void
    {
        $this->range = in_array($r, ['week', 'last_week', '4w'], true) ? $r : 'week';
        unset($this->rows);
    }

    public function render()
    {
        [$from, $to, $label] = $this->bounds();
        $rows = $this->rows;
        $sum = fn (string $k) => array_sum(array_map(fn ($r) => (int) ($r[$k] ?? 0), $rows));

        return view('organization::livewire.agent.effectiveness', [
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'totals' => [
                'done' => $sum('done'),
                'requests' => $sum('requests'),
                'declined' => $sum('declined'),
                'dropped' => $sum('dropped'),
                'corrections' => $sum('corrections'),
                'hours' => round(array_sum(array_map(fn ($r) => (float) $r['hours'], $rows)), 1),
            ],
            'isCurrent' => $to->isToday() || $to->isFuture(),
        ])->layout('platform::layouts.app');
    }
}
