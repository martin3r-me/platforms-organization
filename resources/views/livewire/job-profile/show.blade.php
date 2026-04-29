<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'JobProfiles', 'href' => route('organization.job-profiles.index')],
            ['label' => $jobProfile->name ?? 'Details'],
        ]">
            @if($this->isDirty)
                <x-ui-button variant="secondary-ghost" size="sm" wire:click="loadForm">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-ui-button>
            @else
                @if($jobProfile->status === 'archived')
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="unarchive">
                        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                        <span>Reaktivieren</span>
                    </x-ui-button>
                @else
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="archive" wire:confirm="JobProfile wirklich archivieren?">
                        @svg('heroicon-o-archive-box', 'w-4 h-4')
                        <span>Archivieren</span>
                    </x-ui-button>
                @endif
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                {{-- Status --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <x-ui-badge variant="{{ $jobProfile->status === 'active' ? 'success' : ($jobProfile->status === 'archived' ? 'muted' : 'info') }}" size="sm">
                        {{ ucfirst($jobProfile->status) }}
                    </x-ui-badge>
                </div>

                {{-- Level --}}
                @if($jobProfile->level)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Level</h3>
                        <x-ui-badge variant="secondary" size="sm">{{ ucfirst($jobProfile->level) }}</x-ui-badge>
                    </div>
                @endif

                {{-- Job Family --}}
                @if($jobProfile->job_family)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Job Family</h3>
                        <x-ui-badge variant="secondary" size="sm">{{ $jobProfile->job_family }}</x-ui-badge>
                    </div>
                @endif

                {{-- Owner Entity --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Owner</h3>
                    @if($jobProfile->ownerEntity)
                        <a href="{{ route('organization.entities.show', $jobProfile->ownerEntity) }}" class="text-sm text-[var(--ui-primary)] hover:underline" wire:navigate>
                            {{ $jobProfile->ownerEntity->name }}
                        </a>
                    @else
                        <span class="text-sm text-[var(--ui-muted)]">Kein Owner</span>
                    @endif
                </div>

                {{-- Validity --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Gültigkeit</h3>
                    <div class="text-sm text-[var(--ui-secondary)]">
                        {{ $jobProfile->effective_from?->format('d.m.Y') ?? '—' }}
                        @if($jobProfile->effective_to)
                            – {{ $jobProfile->effective_to->format('d.m.Y') }}
                        @endif
                    </div>
                </div>

                {{-- Created --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Erstellt</h3>
                    <div class="space-y-1">
                        <div class="text-sm text-[var(--ui-secondary)]">{{ $jobProfile->created_at->format('d.m.Y H:i') }}</div>
                        @if($jobProfile->user)
                            <div class="text-xs text-[var(--ui-muted)]">von {{ $jobProfile->user->name }}</div>
                        @endif
                    </div>
                </div>

                {{-- Assignment Stats --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Zuweisungen</h3>
                    <div class="space-y-2">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Personen</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $this->assignmentStats['count'] }}</div>
                        </div>
                        @if($this->assignmentStats['count'] > 0)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Durchschnitt</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $this->assignmentStats['avg_percentage'] }}%</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Grunddaten --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />

                    <x-ui-input-textarea name="description" label="Kurzbeschreibung" wire:model.live="form.description" rows="2" />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                            <select wire:model.live="form.level" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">—</option>
                                <option value="junior">Junior</option>
                                <option value="mid">Mid</option>
                                <option value="senior">Senior</option>
                                <option value="lead">Lead</option>
                                <option value="principal">Principal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select wire:model.live="form.status" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="active">Aktiv</option>
                                <option value="draft">Entwurf</option>
                                <option value="archived">Archiviert</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-ui-input-text name="job_family" label="Job Family" wire:model.live="form.job_family" placeholder="z.B. Engineering, Operations, Sales" />
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Owner (Entity)</label>
                            <select wire:model.live="form.owner_entity_id" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">– Kein Owner –</option>
                                @foreach($this->availableEntities as $entity)
                                    <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-ui-input-text type="date" name="effective_from" label="Gültig ab" wire:model.live="form.effective_from" />
                        <x-ui-input-text type="date" name="effective_to" label="Gültig bis" wire:model.live="form.effective_to" />
                    </div>
                </div>
            </div>

            {{-- Rollenzweck --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Rollenzweck</h2>
                <x-ui-input-textarea name="purpose" wire:model.live="form.purpose" rows="3" placeholder="Warum existiert diese Rolle? Welchen Wert schafft sie?" />
            </div>

            {{-- Kompetenzen (Skills) --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Kompetenzen</h2>
                    <x-ui-button size="xs" variant="secondary-outline" wire:click="addSkill">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5') Hinzufügen
                    </x-ui-button>
                </div>
                @if(!empty($form['skills']))
                    <div class="space-y-2">
                        @foreach($form['skills'] as $i => $skill)
                            <div class="flex items-center gap-2" wire:key="skill-{{ $i }}">
                                <div class="flex-1">
                                    <input type="text" wire:model.live="form.skills.{{ $i }}.name" placeholder="Skill-Name" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <select wire:model.live="form.skills.{{ $i }}.level" class="w-32 rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="basic">Basic</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="expert">Expert</option>
                                </select>
                                <select wire:model.live="form.skills.{{ $i }}.category" class="w-32 rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="technical">Technical</option>
                                    <option value="methodical">Methodical</option>
                                    <option value="domain">Domain</option>
                                </select>
                                <button type="button" wire:click="removeSkill({{ $i }})" class="text-red-500 hover:text-red-700 p-1">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--ui-muted)]">Keine Kompetenzen definiert.</p>
                @endif
            </div>

            {{-- Soft Skills --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Soft Skills</h2>
                    <x-ui-button size="xs" variant="secondary-outline" wire:click="addSoftSkill">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5') Hinzufügen
                    </x-ui-button>
                </div>
                @if(!empty($form['soft_skills']))
                    <div class="space-y-2">
                        @foreach($form['soft_skills'] as $i => $softSkill)
                            <div class="flex items-center gap-2" wire:key="soft-skill-{{ $i }}">
                                <div class="flex-1">
                                    <input type="text" wire:model.live="form.soft_skills.{{ $i }}.name" placeholder="Soft Skill" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <select wire:model.live="form.soft_skills.{{ $i }}.level" class="w-32 rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="basic">Basic</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="expert">Expert</option>
                                </select>
                                <button type="button" wire:click="removeSoftSkill({{ $i }})" class="text-red-500 hover:text-red-700 p-1">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--ui-muted)]">Keine Soft Skills definiert.</p>
                @endif
            </div>

            {{-- Verantwortungen --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Verantwortungen</h2>
                    <x-ui-button size="xs" variant="secondary-outline" wire:click="addResponsibility">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5') Hinzufügen
                    </x-ui-button>
                </div>
                @if(!empty($form['responsibilities']))
                    <div class="space-y-2">
                        @foreach($form['responsibilities'] as $i => $resp)
                            <div class="flex items-center gap-2" wire:key="resp-{{ $i }}">
                                <div class="flex-1">
                                    <input type="text" wire:model.live="form.responsibilities.{{ $i }}.name" placeholder="Verantwortung" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <div class="flex items-center gap-1">
                                    <input type="checkbox" wire:model.live="form.responsibilities.{{ $i }}.is_core" id="resp_core_{{ $i }}" class="rounded border-gray-300">
                                    <label for="resp_core_{{ $i }}" class="text-xs text-[var(--ui-muted)]">Kern</label>
                                </div>
                                <button type="button" wire:click="removeResponsibility({{ $i }})" class="text-red-500 hover:text-red-700 p-1">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--ui-muted)]">Keine Verantwortungen definiert.</p>
                @endif
            </div>

            {{-- Qualifikationen (Requirements) --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Qualifikationen</h2>
                    <x-ui-button size="xs" variant="secondary-outline" wire:click="addRequirement">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5') Hinzufügen
                    </x-ui-button>
                </div>
                @if(!empty($form['requirements']))
                    <div class="space-y-2">
                        @foreach($form['requirements'] as $i => $req)
                            <div class="flex items-center gap-2" wire:key="req-{{ $i }}">
                                <div class="flex-1">
                                    <input type="text" wire:model.live="form.requirements.{{ $i }}.name" placeholder="Qualifikation" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <select wire:model.live="form.requirements.{{ $i }}.type" class="w-36 rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="degree">Abschluss</option>
                                    <option value="certification">Zertifikat</option>
                                    <option value="experience">Erfahrung</option>
                                </select>
                                <div class="flex items-center gap-1">
                                    <input type="checkbox" wire:model.live="form.requirements.{{ $i }}.required" id="req_required_{{ $i }}" class="rounded border-gray-300">
                                    <label for="req_required_{{ $i }}" class="text-xs text-[var(--ui-muted)]">Pflicht</label>
                                </div>
                                <button type="button" wire:click="removeRequirement({{ $i }})" class="text-red-500 hover:text-red-700 p-1">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--ui-muted)]">Keine Qualifikationen definiert.</p>
                @endif
            </div>

            {{-- Bewertungskriterien (KPIs) --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Bewertungskriterien</h2>
                    <x-ui-button size="xs" variant="secondary-outline" wire:click="addKpi">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5') Hinzufügen
                    </x-ui-button>
                </div>
                @if(!empty($form['kpis']))
                    <div class="space-y-2">
                        @foreach($form['kpis'] as $i => $kpi)
                            <div class="flex items-start gap-2" wire:key="kpi-{{ $i }}">
                                <div class="w-1/3">
                                    <input type="text" wire:model.live="form.kpis.{{ $i }}.name" placeholder="KPI-Name" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <div class="flex-1">
                                    <input type="text" wire:model.live="form.kpis.{{ $i }}.description" placeholder="Beschreibung" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <button type="button" wire:click="removeKpi({{ $i }})" class="text-red-500 hover:text-red-700 p-1 mt-1">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--ui-muted)]">Keine Bewertungskriterien definiert.</p>
                @endif
            </div>

            {{-- Ausschlusskriterien --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Ausschlusskriterien</h2>
                    <x-ui-button size="xs" variant="secondary-outline" wire:click="addExclusionCriterion">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5') Hinzufügen
                    </x-ui-button>
                </div>
                @if(!empty($form['exclusion_criteria']))
                    <div class="space-y-2">
                        @foreach($form['exclusion_criteria'] as $i => $criterion)
                            <div class="flex items-center gap-2" wire:key="excl-{{ $i }}">
                                <div class="flex-1">
                                    <input type="text" wire:model.live="form.exclusion_criteria.{{ $i }}" placeholder="z.B. Keine Influencerin" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <button type="button" wire:click="removeExclusionCriterion({{ $i }})" class="text-red-500 hover:text-red-700 p-1">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--ui-muted)]">Keine Ausschlusskriterien definiert.</p>
                @endif
            </div>

            {{-- Arbeitsmodell --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Arbeitsmodell</h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Arbeitsmodell-Typ</label>
                            <select wire:model.live="form.work_model.type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">—</option>
                                <option value="remote">Remote</option>
                                <option value="hybrid">Hybrid</option>
                                <option value="onsite">Vor Ort</option>
                                <option value="travel">Reisend</option>
                            </select>
                        </div>
                        <x-ui-input-text name="work_model_location_notes" label="Standort-Hinweise" wire:model.live="form.work_model.location_notes" placeholder="z.B. Köln, flexibel, weltweit" />
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="form.work_model.travel_required" id="wm_travel" class="rounded border-gray-300">
                            <label for="wm_travel" class="text-sm text-[var(--ui-secondary)]">Reisebereitschaft erforderlich</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="form.work_model.self_organized" id="wm_self" class="rounded border-gray-300">
                            <label for="wm_self" class="text-sm text-[var(--ui-secondary)]">Selbstorganisiert</label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Reporting-Linie / Autonomie --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Reporting & Autonomie</h2>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-text name="reporting_reports_to" label="Berichtet an" wire:model.live="form.reporting.reports_to" placeholder="z.B. CTO, Geschäftsführung, niemand" />
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Autonomie-Level</label>
                        <select wire:model.live="form.reporting.autonomy_level" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">—</option>
                            <option value="full">Full (volle Autonomie)</option>
                            <option value="guided">Guided (begleitet)</option>
                            <option value="supervised">Supervised (angeleitet)</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Ausführliches Profil (Content / Markdown) --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Ausführliches Profil</h2>
                <x-ui-input-textarea name="content" wire:model.live="form.content" rows="8" placeholder="Markdown-Inhalt..." />
            </div>

            {{-- Zuweisungen --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Zuweisungen</h2>

                @if($this->assignments->isNotEmpty())
                    <table class="w-full text-sm mb-4">
                        <thead>
                            <tr class="text-xs text-[var(--ui-muted)] uppercase">
                                <th class="text-left py-1 px-2">Person</th>
                                <th class="text-left py-1 px-2">%</th>
                                <th class="text-left py-1 px-2">Primär</th>
                                <th class="text-left py-1 px-2">Gültig ab</th>
                                <th class="text-left py-1 px-2">Gültig bis</th>
                                <th class="text-left py-1 px-2">Notiz</th>
                                <th class="py-1 px-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->assignments as $a)
                                <tr class="border-t border-[var(--ui-border)]">
                                    <td class="py-1.5 px-2">{{ $a->person?->name ?? '—' }}</td>
                                    <td class="py-1.5 px-2">{{ $a->percentage ?? '—' }}%</td>
                                    <td class="py-1.5 px-2">
                                        @if($a->is_primary)
                                            @svg('heroicon-o-check', 'w-4 h-4 text-green-500')
                                        @else
                                            <span class="text-[var(--ui-muted)]">—</span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 px-2">{{ $a->valid_from?->format('d.m.Y') ?? '—' }}</td>
                                    <td class="py-1.5 px-2">{{ $a->valid_to?->format('d.m.Y') ?? '—' }}</td>
                                    <td class="py-1.5 px-2 text-xs text-[var(--ui-muted)]">{{ $a->note ?? '' }}</td>
                                    <td class="py-1.5 px-2 text-right">
                                        <button type="button" wire:click="deleteAssignment({{ $a->id }})" wire:confirm="Zuweisung wirklich entfernen?" class="text-red-500 hover:text-red-700">
                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-[var(--ui-muted)] mb-4">Keine Zuweisungen vorhanden.</p>
                @endif

                <div class="flex items-end gap-2 flex-wrap border-t border-[var(--ui-border)] pt-3">
                    <div class="w-48">
                        <x-ui-input-select name="assignForm.person_entity_id" label="Person" :options="$this->groupedPersonOptions" wire:model="assignForm.person_entity_id" nullable nullLabel="— Person wählen —" size="sm" />
                    </div>
                    <div class="w-20">
                        <x-ui-input-text name="assignForm.percentage" label="%" wire:model="assignForm.percentage" size="sm" type="number" />
                    </div>
                    <div class="flex items-center gap-1 pb-1">
                        <input type="checkbox" wire:model="assignForm.is_primary" id="assign_primary" class="rounded border-gray-300">
                        <label for="assign_primary" class="text-xs">Primär</label>
                    </div>
                    <div class="w-32">
                        <x-ui-input-text name="assignForm.valid_from" label="Von" wire:model="assignForm.valid_from" size="sm" type="date" />
                    </div>
                    <div class="w-32">
                        <x-ui-input-text name="assignForm.valid_to" label="Bis" wire:model="assignForm.valid_to" size="sm" type="date" />
                    </div>
                    <div class="flex-1 min-w-[120px]">
                        <x-ui-input-text name="assignForm.note" label="Notiz" wire:model="assignForm.note" size="sm" />
                    </div>
                    <div class="pb-0.5">
                        <x-ui-button size="sm" variant="primary" wire:click="storeAssignment">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                        </x-ui-button>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
