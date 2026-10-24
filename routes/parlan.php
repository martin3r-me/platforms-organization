<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Http\Controllers\Api\ParlanController;

// Rückfragen von Parlan (Kontext-Räume): Auth über das Integrations-Geheimnis im Controller.
Route::post('/org/parlan/can-view', [ParlanController::class, 'canView'])->middleware('throttle:120,1');
Route::post('/org/parlan/people', [ParlanController::class, 'people'])->middleware('throttle:120,1');
