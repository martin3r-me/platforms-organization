<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Livewire\Entity\Index as EntityIndex;
use Platform\Organization\Livewire\Entity\Show as EntityShow;
use Platform\Organization\Livewire\CostCenter\Index as CostCenterIndex;
use Platform\Organization\Livewire\CostCenter\Show as CostCenterShow;
use Platform\Organization\Livewire\VsmFunction\Index as VsmFunctionIndex;
use Platform\Organization\Livewire\VsmFunction\Show as VsmFunctionShow;
use Platform\Organization\Livewire\VsmSystem\Index as VsmSystemIndex;
use Platform\Organization\Livewire\VsmSystem\Show as VsmSystemShow;

Route::get('/', Platform\Organization\Livewire\Dashboard::class)->name('organization.dashboard');

Route::get('/entities', EntityIndex::class)->name('organization.entities.index');
Route::get('/entities/{entity}', EntityShow::class)->name('organization.entities.show');

// Dimensionen: Kostenstellen
Route::get('/cost-centers', CostCenterIndex::class)->name('organization.cost-centers.index');
Route::get('/cost-centers/{costCenter}', CostCenterShow::class)->name('organization.cost-centers.show');

// Dimensionen: VSM Funktionen
Route::get('/vsm-functions', VsmFunctionIndex::class)->name('organization.vsm-functions.index');
Route::get('/vsm-functions/{vsmFunction}', VsmFunctionShow::class)->name('organization.vsm-functions.show');

// Dimensionen: VSM Systeme
Route::get('/vsm-systems', VsmSystemIndex::class)->name('organization.vsm-systems.index');
Route::get('/vsm-systems/{vsmSystem}', VsmSystemShow::class)->name('organization.vsm-systems.show');