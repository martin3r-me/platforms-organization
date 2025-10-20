<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Livewire\Entity\Index as EntityIndex;
use Platform\Organization\Livewire\CostCenter\Index as CostCenterIndex;
use Platform\Organization\Livewire\CostCenter\Show as CostCenterShow;
use Platform\Organization\Livewire\VsmSystem\Index as VsmSystemIndex;
use Platform\Organization\Livewire\VsmSystem\Show as VsmSystemShow;

Route::get('/', Platform\Organization\Livewire\Dashboard::class)->name('organization.dashboard');

Route::get('/entities', EntityIndex::class)->name('organization.entities.index');

// Dimensionen: Kostenstellen
Route::get('/cost-centers', CostCenterIndex::class)->name('organization.cost-centers.index');
Route::get('/cost-centers/{costCenter}', CostCenterShow::class)->name('organization.cost-centers.show');

// Dimensionen: VSM Systeme
Route::get('/vsm-systems', VsmSystemIndex::class)->name('organization.vsm-systems.index');
Route::get('/vsm-systems/{vsmSystem}', VsmSystemShow::class)->name('organization.vsm-systems.show');