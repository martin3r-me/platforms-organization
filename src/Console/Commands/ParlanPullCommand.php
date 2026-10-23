<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Platform\Organization\Services\CoordinationIngest;
use Platform\Organization\Support\ParlanContext;

/**
 * Rückweg der Kontext-Kommunikation: holt bei Parlan die Ereignisse der Kontext-Räume DIESER
 * Herkunft ab (GET /api/context/events, authentifiziert mit dem Integrations-Geheimnis — dasselbe
 * Vertrauensverhältnis wie beim Absprung, kein weiterer Token) und speichert sie idempotent.
 * Der Snapshot verortet sie dann über context_ref an der Einheit des Objekts.
 */
class ParlanPullCommand extends Command
{
    protected $signature = 'organization:parlan-pull {--days=2 : Fenster in Tagen (1–14), idempotent}';

    protected $description = 'Kontext-Kommunikation aus Parlan abholen (Rückweg in die Organisation)';

    public function handle(): int
    {
        if (! ParlanContext::enabled()) {
            $this->line('Parlan nicht konfiguriert (organization.parlan.url/secret) — nichts zu tun.');

            return self::SUCCESS;
        }
        $res = Http::withToken((string) config('organization.parlan.secret'))
            ->withHeaders(['X-Parlan-Origin' => ParlanContext::origin()])
            ->acceptJson()->timeout(30)
            ->get(rtrim((string) config('organization.parlan.url'), '/') . '/api/context/events', ['days' => (int) $this->option('days')]);
        if (! $res->successful()) {
            $this->error('Abholen fehlgeschlagen: HTTP ' . $res->status());

            return self::FAILURE;
        }
        $events = (array) ($res->json('events') ?? []);
        $stored = 0;
        $unresolved = [];
        foreach (array_chunk($events, 500) as $chunk) {
            $r = CoordinationIngest::store($chunk);
            $stored += $r['stored'];
            $unresolved = array_merge($unresolved, $r['unresolved']);
        }
        $this->info("Kontext-Kommunikation: {$stored} von " . count($events) . ' Ereignissen gespeichert'
            . ($unresolved ? ' · ohne Org-User: ' . implode(', ', array_unique($unresolved)) : ''));

        return self::SUCCESS;
    }
}
