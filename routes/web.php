<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Livewire\Entity\Index as EntityIndex;
use Platform\Organization\Livewire\Entity\Show as EntityShow;
use Platform\Organization\Livewire\CostCenter\Index as CostCenterIndex;
use Platform\Organization\Livewire\CostCenter\Show as CostCenterShow;
use Platform\Organization\Livewire\VsmSystem\Index as VsmSystemIndex;
use Platform\Organization\Livewire\VsmSystem\Show as VsmSystemShow;
use Platform\Organization\Livewire\Settings\EntityType\Index as EntityTypeIndex;
use Platform\Organization\Livewire\Settings\EntityType\Show as EntityTypeShow;
use Platform\Organization\Livewire\Settings\EntityTypeGroup\Index as EntityTypeGroupIndex;
use Platform\Organization\Livewire\Settings\EntityTypeGroup\Show as EntityTypeGroupShow;
use Platform\Organization\Livewire\Settings\RelationType\Index as RelationTypeIndex;
use Platform\Organization\Livewire\Settings\RelationType\Show as RelationTypeShow;
use Platform\Organization\Livewire\TimeEntries\Index as TimeEntriesIndex;
use Platform\Organization\Livewire\PlannedTimes\Index as PlannedTimesIndex;

Route::get('/', Platform\Organization\Livewire\Dashboard::class)->name('organization.dashboard');

Route::get('/entities', EntityIndex::class)->name('organization.entities.index');
Route::get('/entities/{entity}', EntityShow::class)->name('organization.entities.show');

// Dimensionen: Kostenstellen
Route::get('/cost-centers', CostCenterIndex::class)->name('organization.cost-centers.index');
Route::get('/cost-centers/{costCenter}', CostCenterShow::class)->name('organization.cost-centers.show');

// Dimensionen: VSM Systeme
Route::get('/vsm-systems', VsmSystemIndex::class)->name('organization.vsm-systems.index');
Route::get('/vsm-systems/{vsmSystem}', VsmSystemShow::class)->name('organization.vsm-systems.show');

// Settings: Entity Types
Route::get('/settings/entity-types', EntityTypeIndex::class)->name('organization.settings.entity-types.index');
Route::get('/settings/entity-types/{entityType}', EntityTypeShow::class)->name('organization.settings.entity-types.show');

// Settings: Entity Type Groups
Route::get('/settings/entity-type-groups', EntityTypeGroupIndex::class)->name('organization.settings.entity-type-groups.index');
Route::get('/settings/entity-type-groups/{entityTypeGroup}', EntityTypeGroupShow::class)->name('organization.settings.entity-type-groups.show');

// Settings: Relation Types
Route::get('/settings/relation-types', RelationTypeIndex::class)->name('organization.settings.relation-types.index');
Route::get('/settings/relation-types/{relationType}', RelationTypeShow::class)->name('organization.settings.relation-types.show');

// Zeiten: Ist-Zeiten und Geplante Zeiten
Route::get('/time-entries', TimeEntriesIndex::class)->name('organization.time-entries.index');
Route::get('/planned-times', PlannedTimesIndex::class)->name('organization.planned-times.index');