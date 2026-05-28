<?php

namespace Platform\Organization\Livewire\Settings\SignalDefinition;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSignalDefinition;

class Index extends Component
{
    public $search = '';
    public $showInactive = false;
    public $modalShow = false;

    public $form = [
        'name' => '',
        'description' => '',
        'pattern_type' => 'threshold',
        'conditions' => [],
        'scope_type' => 'all',
        'scope_value' => null,
        'frequency' => 'every_snapshot',
        'severity' => 'warning',
        'is_active' => true,
    ];

    // Condition sub-fields for dynamic UI
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

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    #[Computed]
    public function signalDefinitions()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationSignalDefinition::forTeam($teamId);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if (! $this->showInactive) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    public function create()
    {
        $this->reset('form');
        $this->form['is_active'] = true;
        $this->form['pattern_type'] = 'threshold';
        $this->form['scope_type'] = 'all';
        $this->form['frequency'] = 'every_snapshot';
        $this->form['severity'] = 'warning';
        $this->resetConditionFields();
        $this->modalShow = true;
    }

    protected function resetConditionFields()
    {
        $this->conditionMetric = '';
        $this->conditionOperator = '>';
        $this->conditionValue = '';
        $this->conditionDirection = 'increasing';
        $this->conditionPeriods = 3;
        $this->conditionMinChange = 10;
        $this->conditionMetricA = '';
        $this->conditionMetricB = '';
        $this->conditionRelationship = 'diverging';
        $this->conditionNumerator = '';
        $this->conditionDenominator = '';
        $this->scopeValueInput = '';
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

        // Parse comma-separated values
        return array_map('trim', explode(',', $raw));
    }

    public function store()
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.pattern_type' => ['required', 'in:threshold,trend,cross_dimension,ratio'],
            'form.scope_type' => ['required', 'in:all,entity_type,entity_ids,subtree'],
            'form.frequency' => ['required', 'in:every_snapshot,daily,weekly'],
            'form.severity' => ['required', 'in:info,warning,critical'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string'],
        ]);

        $conditions = $this->buildConditions();
        $scopeValue = $this->parseScopeValue();

        OrganizationSignalDefinition::create([
            'name' => $this->form['name'],
            'description' => $this->form['description'] ?: null,
            'pattern_type' => $this->form['pattern_type'],
            'conditions' => $conditions,
            'scope_type' => $this->form['scope_type'],
            'scope_value' => $scopeValue,
            'frequency' => $this->form['frequency'],
            'severity' => $this->form['severity'],
            'is_active' => $this->form['is_active'],
        ]);

        $this->modalShow = false;
        $this->dispatch('toast', message: 'Signal-Definition erstellt');
    }

    public function toggleInactive()
    {
        $this->showInactive = ! $this->showInactive;
    }

    public function render()
    {
        return view('organization::livewire.settings.signal-definition.index')
            ->layout('platform::layouts.app');
    }
}
