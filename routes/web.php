<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Livewire\Entity\Index as EntityIndex;

Route::get('/', Platform\Organization\Livewire\Dashboard::class)->name('organization.dashboard');

Route::get('/entities', EntityIndex::class)->name('organization.entities.index');