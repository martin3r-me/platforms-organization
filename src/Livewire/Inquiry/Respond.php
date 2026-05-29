<?php

namespace Platform\Organization\Livewire\Inquiry;

use Livewire\Component;
use Platform\Organization\Models\OrganizationInquiryRecipient;
use Platform\Organization\Models\OrganizationInferenceTrigger;
use Platform\Organization\Models\OrganizationMemoryEntry;

class Respond extends Component
{
    public OrganizationInquiryRecipient $recipient;
    public array $responses = [];

    public function mount(OrganizationInquiryRecipient $recipient): void
    {
        abort_unless($recipient->recipient_user_id === auth()->id(), 403);
        abort_unless($recipient->status !== 'answered', 410);

        $this->recipient = $recipient;

        // Initialize responses with empty values for each field
        foreach ($recipient->inquiry->fields ?? [] as $field) {
            $this->responses[$field['key']] = $field['type'] === 'boolean' ? false : '';
        }
    }

    public function submit(): void
    {
        $inquiry = $this->recipient->inquiry;

        // Validate required fields
        foreach ($inquiry->fields ?? [] as $field) {
            if (empty($field['optional']) && $field['type'] !== 'boolean') {
                if (empty($this->responses[$field['key']] ?? '')) {
                    $this->addError("responses.{$field['key']}", 'Dieses Feld ist erforderlich.');
                    return;
                }
            }
        }

        // Save response
        $this->recipient->update([
            'response' => $this->responses,
            'response_at' => now(),
            'status' => 'answered',
        ]);

        // Check if inquiry is completed
        $completed = $inquiry->checkCompletion();

        // Create memory entry
        $this->createMemoryEntry($inquiry);

        // Trigger re-evaluation if completed
        if ($completed) {
            OrganizationInferenceTrigger::createDebounced([
                'team_id' => $inquiry->team_id,
                'trigger_type' => 'inquiry_answered',
                'trigger_reference' => $inquiry->id,
                'prompt_filter' => $inquiry->inference_prompt_id
                    ? ['prompt_ids' => [$inquiry->inference_prompt_id]]
                    : null,
                'entity_filter' => ['entity_ids' => [$inquiry->entity_id]],
                'priority' => 60,
                'status' => 'pending',
                'debounce_key' => "inquiry_answered:{$inquiry->id}",
            ]);
        }

        session()->flash('success', 'Antwort erfolgreich gespeichert.');
        $this->redirect(route('organization.my-inquiries.index'), navigate: true);
    }

    protected function createMemoryEntry($inquiry): void
    {
        try {
            $recipientName = $this->recipient->recipientEntity?->name ?? 'Unbekannt';
            $responseText = collect($this->responses)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');

            OrganizationMemoryEntry::create([
                'team_id' => $inquiry->team_id,
                'entity_id' => $inquiry->entity_id,
                'inference_prompt_id' => $inquiry->inference_prompt_id,
                'memory_type' => 'inquiry_outcome',
                'content' => "Inquiry-Antwort von {$recipientName}: {$responseText}",
                'structured_data' => [
                    'inquiry_id' => $inquiry->id,
                    'inquiry_type' => $inquiry->inquiry_type,
                    'recipient_entity_id' => $this->recipient->recipient_entity_id,
                    'response' => $this->responses,
                ],
                'confidence' => 0.7,
                'source_type' => 'inquiry_response',
                'source_id' => $inquiry->id,
                'valid_until' => now()->addDays(90),
                'is_active' => true,
            ]);
        } catch (\Throwable) {
            // Memory creation should never block response saving
        }
    }

    public function render()
    {
        return view('organization::livewire.inquiry.respond')
            ->layout('platform::layouts.app');
    }
}
