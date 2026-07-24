<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Http\Controllers\EntityStrategyPublicController;

// Öffentliche Strategie-Ansicht eines Organisations-Knotens (Carrier-Entity).
// Token-basiert, ohne Login – teilbar + als PDF.
Route::get('/org/{token}', [EntityStrategyPublicController::class, 'show'])->name('organization.public.strategy');
Route::get('/org/{token}/pdf', [EntityStrategyPublicController::class, 'pdf'])->name('organization.public.strategy.pdf');
