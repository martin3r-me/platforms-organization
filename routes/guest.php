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
// Belege und Profilbilder hinter der Freigabe — vor der Detail-Route, die sonst „person/{id}" abfängt.
Route::get('/teilen/{token}/beleg/{ref}', [\Platform\Organization\Http\Controllers\ShareFileController::class, 'document'])
    ->where(['token' => '[a-f0-9]{48}', 'ref' => '[a-z0-9_-]+'])
    ->name('organization.share.document');
Route::get('/teilen/{token}/person/{user}', [\Platform\Organization\Http\Controllers\ShareFileController::class, 'avatar'])
    ->where(['token' => '[a-f0-9]{48}', 'user' => '[0-9]+'])
    ->name('organization.share.avatar');

Route::get('/teilen/{token}/{type}/{id}/{sub?}', \Platform\Organization\Livewire\Share\PublicShare::class)
    ->where(['token' => '[a-f0-9]{48}', 'type' => '[a-z0-9_-]+', 'id' => '[0-9]+', 'sub' => '[a-z0-9_-]+'])
    ->name('organization.share.item');
// Bereichsseiten: leistung | rechnungen | aktivitaet oder ein Modul-Bereich (z. B. sichtbarkeit), optional mit Unterseite
Route::get('/teilen/{token}/{section}/{sub?}', \Platform\Organization\Livewire\Share\PublicShare::class)
    ->where(['token' => '[a-f0-9]{48}', 'section' => '[a-z][a-z0-9-]*', 'sub' => '[a-z][a-z0-9_-]*'])
    ->name('organization.share.section');
