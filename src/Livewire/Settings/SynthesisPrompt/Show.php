<?php

namespace Platform\Organization\Livewire\Settings\SynthesisPrompt;

use Livewire\Component;
use Platform\Organization\Models\OrganizationSynthesisPromptDefinition;

class Show extends Component
{
    public OrganizationSynthesisPromptDefinition $definition;

    public string $name = '';
    public ?string $description = null;
    public string $report_type = 'weekly';
    public string $system_prompt = '';
    public string $user_message_template = '';
    public int $max_signals = 100;
    public ?string $model = null;
    public int $max_tokens = 8192;
    public bool $is_active = true;

    public ?string $savedMessage = null;

    public function mount(OrganizationSynthesisPromptDefinition $definition): void
    {
        $this->definition = $definition;
        $this->name = $definition->name;
        $this->description = $definition->description;
        $this->report_type = $definition->report_type;
        $this->system_prompt = $definition->system_prompt;
        $this->user_message_template = $definition->user_message_template;
        $this->max_signals = $definition->max_signals;
        $this->model = $definition->model;
        $this->max_tokens = $definition->max_tokens;
        $this->is_active = $definition->is_active;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'report_type' => 'required|in:weekly,monthly,quarterly',
            'system_prompt' => 'required|string',
            'user_message_template' => 'required|string',
            'max_signals' => 'required|integer|min:1|max:1000',
            'model' => 'nullable|string|max:100',
            'max_tokens' => 'required|integer|min:512|max:32768',
            'is_active' => 'boolean',
        ]);

        $this->definition->update([
            'name' => $this->name,
            'description' => $this->description,
            'report_type' => $this->report_type,
            'system_prompt' => $this->system_prompt,
            'user_message_template' => $this->user_message_template,
            'max_signals' => $this->max_signals,
            'model' => $this->model ?: null,
            'max_tokens' => $this->max_tokens,
            'is_active' => $this->is_active,
        ]);

        $this->savedMessage = 'Gespeichert um ' . now()->format('H:i:s');
    }

    public function resetToDefault(string $field): void
    {
        match ($field) {
            'system_prompt' => $this->system_prompt = OrganizationSynthesisPromptDefinition::DEFAULT_SYSTEM_PROMPT,
            'user_message_template' => $this->user_message_template = OrganizationSynthesisPromptDefinition::DEFAULT_USER_TEMPLATE,
            default => null,
        };
    }

    public function render()
    {
        return view('organization::livewire.settings.synthesis-prompt.show')
            ->layout('platform::layouts.app');
    }
}
