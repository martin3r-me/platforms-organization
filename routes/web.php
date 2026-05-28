<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Livewire\Entity\Index as EntityIndex;
use Platform\Organization\Livewire\Entity\Show as EntityShow;
use Platform\Organization\Livewire\Entity\Mindmap as EntityMindmap;
use Platform\Organization\Livewire\Entity\Board as EntityBoard;
use Platform\Organization\Livewire\CostCenter\Index as CostCenterIndex;
use Platform\Organization\Livewire\CostCenter\Show as CostCenterShow;
use Platform\Organization\Livewire\Perspective\Index as PerspectiveIndex;
use Platform\Organization\Livewire\Perspective\Show as PerspectiveShow;
use Platform\Organization\Livewire\Settings\EntityType\Index as EntityTypeIndex;
use Platform\Organization\Livewire\Settings\EntityType\Show as EntityTypeShow;
use Platform\Organization\Livewire\Settings\EntityTypeGroup\Index as EntityTypeGroupIndex;
use Platform\Organization\Livewire\Settings\EntityTypeGroup\Show as EntityTypeGroupShow;
use Platform\Organization\Livewire\Settings\RelationType\Index as RelationTypeIndex;
use Platform\Organization\Livewire\Settings\RelationType\Show as RelationTypeShow;
use Platform\Organization\Livewire\Settings\InterlinkCategory\Index as InterlinkCategoryIndex;
use Platform\Organization\Livewire\Settings\InterlinkCategory\Show as InterlinkCategoryShow;
use Platform\Organization\Livewire\Settings\InterlinkType\Index as InterlinkTypeIndex;
use Platform\Organization\Livewire\Settings\InterlinkType\Show as InterlinkTypeShow;
use Platform\Organization\Livewire\Interlink\Index as InterlinkIndex;
use Platform\Organization\Livewire\Interlink\Show as InterlinkShow;
use Platform\Organization\Livewire\SlaContract\Index as SlaContractIndex;
use Platform\Organization\Livewire\SlaContract\Show as SlaContractShow;
use Platform\Organization\Livewire\TimeEntries\Index as TimeEntriesIndex;
use Platform\Organization\Livewire\PlannedTimes\Index as PlannedTimesIndex;
use Platform\Organization\Livewire\JobProfile\Index as JobProfileIndex;
use Platform\Organization\Livewire\JobProfile\Show as JobProfileShow;
use Platform\Organization\Livewire\Role\Index as RoleIndex;
use Platform\Organization\Livewire\Settings\SignalDefinition\Index as SignalDefinitionIndex;
use Platform\Organization\Livewire\Settings\SignalDefinition\Show as SignalDefinitionShow;
use Platform\Organization\Livewire\Skill\Index as SkillIndex;

Route::get('/', Platform\Organization\Livewire\Dashboard::class)->name('organization.dashboard');

Route::get('/entities', EntityIndex::class)->name('organization.entities.index');
Route::get('/entities/{entity}', EntityShow::class)->name('organization.entities.show');
Route::get('/entities/{entity}/mindmap', EntityMindmap::class)->name('organization.entities.mindmap');
Route::get('/entities/{entity}/board', EntityBoard::class)->name('organization.entities.board');

// Dimensionen: Kostenstellen
Route::get('/cost-centers', CostCenterIndex::class)->name('organization.cost-centers.index');
Route::get('/cost-centers/{costCenter}', CostCenterShow::class)->name('organization.cost-centers.show');

// Dimensionen: Perspektiven
Route::get('/perspectives', PerspectiveIndex::class)->name('organization.perspectives.index');
Route::get('/perspectives/{perspective}', PerspectiveShow::class)->name('organization.perspectives.show');

// Settings: Entity Types
Route::get('/settings/entity-types', EntityTypeIndex::class)->name('organization.settings.entity-types.index');
Route::get('/settings/entity-types/{entityType}', EntityTypeShow::class)->name('organization.settings.entity-types.show');

// Settings: Entity Type Groups
Route::get('/settings/entity-type-groups', EntityTypeGroupIndex::class)->name('organization.settings.entity-type-groups.index');
Route::get('/settings/entity-type-groups/{entityTypeGroup}', EntityTypeGroupShow::class)->name('organization.settings.entity-type-groups.show');

// Settings: Relation Types
Route::get('/settings/relation-types', RelationTypeIndex::class)->name('organization.settings.relation-types.index');
Route::get('/settings/relation-types/{relationType}', RelationTypeShow::class)->name('organization.settings.relation-types.show');

// Settings: Interlink-Kategorien
Route::get('/settings/interlink-categories', InterlinkCategoryIndex::class)->name('organization.settings.interlink-categories.index');
Route::get('/settings/interlink-categories/{interlinkCategory}', InterlinkCategoryShow::class)->name('organization.settings.interlink-categories.show');

// Settings: Interlink-Typen
Route::get('/settings/interlink-types', InterlinkTypeIndex::class)->name('organization.settings.interlink-types.index');
Route::get('/settings/interlink-types/{interlinkType}', InterlinkTypeShow::class)->name('organization.settings.interlink-types.show');

// Settings: Signal-Definitionen
Route::get('/settings/signal-definitions', SignalDefinitionIndex::class)->name('organization.settings.signal-definitions.index');
Route::get('/settings/signal-definitions/{signalDefinition}', SignalDefinitionShow::class)->name('organization.settings.signal-definitions.show');

// Interlinks
Route::get('/interlinks', InterlinkIndex::class)->name('organization.interlinks.index');
Route::get('/interlinks/{interlink}', InterlinkShow::class)->name('organization.interlinks.show');

// SLA-Verträge
Route::get('/sla-contracts', SlaContractIndex::class)->name('organization.sla-contracts.index');
Route::get('/sla-contracts/{slaContract}', SlaContractShow::class)->name('organization.sla-contracts.show');

// Zeiten: Ist-Zeiten und Geplante Zeiten
Route::get('/time-entries', TimeEntriesIndex::class)->name('organization.time-entries.index');
Route::get('/planned-times', PlannedTimesIndex::class)->name('organization.planned-times.index');

// Personen-Katalog: JobProfiles und Rollen
Route::get('/job-profiles', JobProfileIndex::class)->name('organization.job-profiles.index');
Route::get('/job-profiles/{jobProfile}', JobProfileShow::class)->name('organization.job-profiles.show');
Route::get('/roles', RoleIndex::class)->name('organization.roles.index');
Route::get('/skills', SkillIndex::class)->name('organization.skills.index');

// Error Tracking Test (temporär)
Route::get('/test-error/{type?}', function (string $type = 'exception') {
    return match ($type) {
        '500' => abort(500, 'Organization Test: Simulated 500 Internal Server Error'),
        '404' => abort(404, 'Organization Test: Simulated 404 Not Found'),
        '403' => abort(403, 'Organization Test: Simulated 403 Forbidden'),
        'runtime' => throw new \RuntimeException('Organization Test: Simulated RuntimeException'),
        'logic' => throw new \LogicException('Organization Test: Simulated LogicException'),
        'db' => throw new \Illuminate\Database\QueryException('mysql', 'SELECT * FROM non_existent_table', [], new \Exception('Table not found')),
        default => throw new \Platform\Organization\Exceptions\OrganizationTestException('Organization Test: Simulated exception at ' . now()->toIso8601String()),
    };
})->name('organization.test-error');