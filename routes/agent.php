<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Http\Controllers\Api\AgentChangeController;
use Platform\Organization\Http\Controllers\Api\AgentEnvironmentController;
use Platform\Organization\Http\Controllers\Api\AgentMessagingController;
use Platform\Organization\Http\Controllers\Api\AgentOkrController;
use Platform\Organization\Http\Controllers\Api\AgentPortfolioController;
use Platform\Organization\Http\Controllers\Api\AgentPulseController;
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
    // Dashboard-Kennzahlen: getrackte Zeit (24h / Monat).
    Route::get('/stats', [AgentProfileController::class, 'stats'])->name('organization.api.agent.stats');
    // Observability: jüngste Run-Events lesen (z. B. ?kind=fail für Ablehnungsgründe).
    Route::get('/events', [AgentProfileController::class, 'events'])->name('organization.api.agent.events');
    // FOKUS & ZIELE: die eigenen OKRs des aktuellen Zyklus ziehen (Firmware lädt sie als DNA-Achse).
    Route::get('/okrs', [AgentOkrController::class, 'okrs'])->name('organization.api.agent.okrs');
    // WELTBILD: das Venture-Portfolio (Strategie-Briefs) ziehen → der Schlaf verinnerlicht es.
    Route::get('/portfolio', [AgentPortfolioController::class, 'portfolio'])->name('organization.api.agent.portfolio');
    // RICHTUNG: die aktiven Transformationen (Change-Vorhaben) ziehen → verinnerlichen + als Vektor im Primer.
    Route::get('/changes', [AgentChangeController::class, 'changes'])->name('organization.api.agent.changes');
    // UMWELT: das Sensor-Feld (Außen + Bewegung) für S4/Perceptor — Deltas als Signal-Rohstoff.
    Route::get('/environment', [AgentEnvironmentController::class, 'environment'])->name('organization.api.agent.environment');
    // PULSE: die operative Innen-Lage (was brennt / was kippt) für S3/S2 (Steward).
    Route::get('/pulse', [AgentPulseController::class, 'pulse'])->name('organization.api.agent.pulse');
    // COMMS (AUSGANG): der Ausgangs-Motor — Inhalt rein, Org rendert Hausstil + sendet über M365
    // (Teams/Mail) als der Agent selbst. Transport-agnostisch, Terminal-Chat wird abgelöst.
    Route::post('/message', [AgentMessagingController::class, 'send'])->name('organization.api.agent.message');
    // COMMS (EINGANG): der Eingangs-Sinn — EIN Posteingang aus Teams + Mail seit last-seen (pull).
    Route::get('/messages', [AgentMessagingController::class, 'inbox'])->name('organization.api.agent.messages');
    // ROSTER: die Kollegen-Agenten (Adresse + Rolle) für gezielte laterale 1:1-DMs.
    Route::get('/roster', [AgentMessagingController::class, 'roster'])->name('organization.api.agent.roster');
});
