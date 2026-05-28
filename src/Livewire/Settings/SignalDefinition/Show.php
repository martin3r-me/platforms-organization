<?php

namespace Platform\Organization\Livewire\Settings\SignalDefinition;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalDefinition;

class Show extends Component
{
    public OrganizationSignalDefinition $signalDefinition;

    public array $form = [];

    // Condition sub-fields
    public $conditionMetric = '';
    public $conditionOperator = '>';
    public $conditionValue = '';
    public $conditionDirection = 'increasing';
    public $conditionPeriods = 3;
    public $conditionMinChange = 10;
    public $conditionMetricA = '';
    public $conditionMetricB = '';
    public $conditionRelationship = 'diverging';
    public $conditionNumerator = '';
    public $conditionDenominator = '';

    public $scopeValueInput = '';

    public function mount(OrganizationSignalDefinition $signalDefinition)
    {
        $this->signalDefinition = $signalDefinition;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->signalDefinition->name,
            'description' => $this->signalDefinition->description ?? '',
            'pattern_type' => $this->signalDefinition->pattern_type,
            'scope_type' => $this->signalDefinition->scope_type,
            'frequency' => $this->signalDefinition->frequency,
            'severity' => $this->signalDefinition->severity,
            'is_active' => $this->signalDefinition->is_active,
        ];

        $this->loadConditionFields();
        $this->scopeValueInput = $this->signalDefinition->scope_value
            ? implode(', ', $this->signalDefinition->scope_value)
            : '';
    }

    protected function loadConditionFields()
    {
        $conditions = $this->signalDefinition->conditions ?? [];

        match ($this->signalDefinition->pattern_type) {
            'threshold' => (function () use ($conditions) {
                $this->conditionMetric = $conditions['metric'] ?? '';
                $this->conditionOperator = $conditions['operator'] ?? '>';
                $this->conditionValue = $conditions['value'] ?? '';
            })(),
            'trend' => (function () use ($conditions) {
                $this->conditionMetric = $conditions['metric'] ?? '';
                $this->conditionDirection = $conditions['direction'] ?? 'increasing';
                $this->conditionPeriods = $conditions['periods'] ?? 3;
                $this->conditionMinChange = $conditions['min_change_percent'] ?? 10;
            })(),
            'cross_dimension' => (function () use ($conditions) {
                $this->conditionMetricA = $conditions['metric_a'] ?? '';
                $this->conditionMetricB = $conditions['metric_b'] ?? '';
                $this->conditionRelationship = $conditions['relationship'] ?? 'diverging';
            })(),
            'ratio' => (function () use ($conditions) {
                $this->conditionNumerator = $conditions['numerator'] ?? '';
                $this->conditionDenominator = $conditions['denominator'] ?? '';
                $this->conditionOperator = $conditions['operator'] ?? '<';
                $this->conditionValue = $conditions['value'] ?? 0.5;
            })(),
            default => null,
        };
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->signalDefinition->name ||
               ($this->form['description'] ?? '') !== ($this->signalDefinition->description ?? '') ||
               $this->form['pattern_type'] !== $this->signalDefinition->pattern_type ||
               $this->form['scope_type'] !== $this->signalDefinition->scope_type ||
               $this->form['frequency'] !== $this->signalDefinition->frequency ||
               $this->form['severity'] !== $this->signalDefinition->severity ||
               $this->form['is_active'] !== $this->signalDefinition->is_active;
    }

    #[Computed]
    public function recentSignals()
    {
        return OrganizationSignal::where('signal_definition_id', $this->signalDefinition->id)
            ->with('entity')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    protected function buildConditions(): array
    {
        return match ($this->form['pattern_type']) {
            'threshold' => [
                'metric' => $this->conditionMetric,
                'operator' => $this->conditionOperator,
                'value' => (float) $this->conditionValue,
            ],
            'trend' => [
                'metric' => $this->conditionMetric,
                'direction' => $this->conditionDirection,
                'periods' => (int) $this->conditionPeriods,
                'min_change_percent' => (float) $this->conditionMinChange,
            ],
            'cross_dimension' => [
                'metric_a' => $this->conditionMetricA,
                'metric_b' => $this->conditionMetricB,
                'relationship' => $this->conditionRelationship,
            ],
            'ratio' => [
                'numerator' => $this->conditionNumerator,
                'denominator' => $this->conditionDenominator,
                'operator' => $this->conditionOperator,
                'value' => (float) $this->conditionValue,
            ],
            default => [],
        };
    }

    protected function parseScopeValue(): ?array
    {
        if ($this->form['scope_type'] === 'all') {
            return null;
        }

        $raw = trim($this->scopeValueInput);
        if ($raw === '') {
            return null;
        }

        return array_map('trim', explode(',', $raw));
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.pattern_type' => 'required|in:threshold,trend,cross_dimension,ratio',
            'form.scope_type' => 'required|in:all,entity_type,entity_ids,subtree',
            'form.frequency' => 'required|in:every_snapshot,daily,weekly',
            'form.severity' => 'required|in:info,warning,critical',
            'form.is_active' => 'boolean',
            'form.description' => 'nullable|string',
        ]);

        try {
            $this->signalDefinition->update([
                'name' => $this->form['name'],
                'description' => $this->form['description'] ?: null,
                'pattern_type' => $this->form['pattern_type'],
                'conditions' => $this->buildConditions(),
                'scope_type' => $this->form['scope_type'],
                'scope_value' => $this->parseScopeValue(),
                'frequency' => $this->form['frequency'],
                'severity' => $this->form['severity'],
                'is_active' => $this->form['is_active'],
            ]);
            $this->loadForm();
            $this->dispatch('toast', message: 'Signal-Definition gespeichert');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Speichern: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function delete()
    {
        try {
            $this->signalDefinition->delete();
            $this->dispatch('toast', message: 'Signal-Definition gelöscht');
            return redirect()->route('organization.settings.signal-definitions.index');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Löschen: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function render()
    {
        return view('organization::livewire.settings.signal-definition.show')
            ->layout('platform::layouts.app');
    }
}
