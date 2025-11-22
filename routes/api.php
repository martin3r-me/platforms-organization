<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Http\Controllers\Api\TimeEntryDatawarehouseController;
use Platform\Organization\Http\Controllers\Api\TimePlannedDatawarehouseController;

/**
 * Organization API Routes
 * 
 * Datawarehouse-Endpunkte für IST- und SOLL-Zeiten
 */
Route::get('/time-entries/datawarehouse', [TimeEntryDatawarehouseController::class, 'index']);
Route::get('/time-planned/datawarehouse', [TimePlannedDatawarehouseController::class, 'index']);

