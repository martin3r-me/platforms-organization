<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Http\Controllers\Api\AgentTimeController;

/**
 * Agent-API des Organization-Moduls — bewusst wie dev/helpdesk aufgebaut (auth:api,
 * Worker-Token). Der generische Zeit-Stempel für alle autonomen Rollen.
 */
Route::prefix('org/agent')->middleware('auth:api')->group(function () {
    Route::post('/time', [AgentTimeController::class, 'stamp'])->name('organization.api.agent.time');
});
