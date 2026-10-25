<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Http\Controllers\EntityStrategyPublicController;

// Öffentliche Strategie-Ansicht eines Organisations-Knotens (Carrier-Entity).
// Token-basiert, ohne Login – teilbar + als PDF.
Route::get('/org/{token}', [EntityStrategyPublicController::class, 'show'])->name('organization.public.strategy');
Route::get('/org/{token}/pdf', [EntityStrategyPublicController::class, 'pdf'])->name('organization.public.strategy.pdf');

// Kunden-Tor: geteilter Knoten mit allem, was daran hängt (Freigabe- oder persönlicher Link).
Route::get('/teilen/{token}', \Platform\Organization\Livewire\Share\PublicShare::class)
    ->where('token', '[a-f0-9]{48}')
    ->name('organization.share.public');
Route::get('/teilen/{token}/{type}/{id}/{sub?}', \Platform\Organization\Livewire\Share\PublicShare::class)
    ->where(['token' => '[a-f0-9]{48}', 'type' => '[a-z0-9_-]+', 'id' => '[0-9]+', 'sub' => '[a-z0-9_-]+'])
    ->name('organization.share.item');
