<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Http\Controllers\Api\AgentKnowledgeController;
use Platform\Organization\Http\Controllers\Api\AgentProfileController;
use Platform\Organization\Http\Controllers\Api\AgentTimeController;

/**
 * Agent-API des Organization-Moduls — bewusst wie dev/helpdesk aufgebaut (auth:api,
 * Bot-User-Token). Zeit-Stempel + der Agent-Vertrag (Config ziehen / Status melden).
 */
Route::prefix('org/agent')->middleware('auth:api')->group(function () {
    Route::post('/time', [AgentTimeController::class, 'stamp'])->name('organization.api.agent.time');
    // Client-Daemon: seine Config ziehen + Status/Heartbeat melden.
    Route::get('/profile', [AgentProfileController::class, 'profile'])->name('organization.api.agent.profile');
    Route::post('/heartbeat', [AgentProfileController::class, 'heartbeat'])->name('organization.api.agent.heartbeat');
    // Client-Daemon: seinen Aktivitäts-Feed melden (Live-Log, kein Voll-Token-Strom).
    Route::post('/log', [AgentProfileController::class, 'log'])->name('organization.api.agent.log');
    // Client-Daemon: einen gebündelten Gehirn-Snapshot pushen → Org-Einzel-Gehirn-Ansicht (host-agnostisch).
    Route::post('/brain', [AgentProfileController::class, 'brain'])->name('organization.api.agent.brain');
    // Learn-Loop: Domänen-Wissen ziehen (beim Claim) + ablegen (nach dem Run).
    Route::get('/knowledge', [AgentKnowledgeController::class, 'index'])->name('organization.api.agent.knowledge.index');
    Route::post('/knowledge', [AgentKnowledgeController::class, 'store'])->name('organization.api.agent.knowledge.store');
    // Dashboard-Kennzahlen: getrackte Zeit (24h / Monat).
    Route::get('/stats', [AgentProfileController::class, 'stats'])->name('organization.api.agent.stats');
    // Observability: jüngste Run-Events lesen (z. B. ?kind=fail für Ablehnungsgründe).
    Route::get('/events', [AgentProfileController::class, 'events'])->name('organization.api.agent.events');
});
