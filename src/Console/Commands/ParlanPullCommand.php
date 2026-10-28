<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Platform\Organization\Services\CoordinationIngest;
use Platform\Organization\Services\EntityLinkRegistry;
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

        $this->syncDone();

        return self::SUCCESS;
    }

    /**
     * Gegenrichtung (rooms/0.3): Parlan erfährt, welche Objekte erledigt sind — deren Gespräch
     * ruht dann für alle; wieder offen → es wacht auf. Nur die Refs, zu denen es einen Raum gibt;
     * Parlan macht nur aus Änderungen Ereignisse. Ein gelöschtes Objekt gilt als erledigt.
     */
    private function syncDone(): void
    {
        $base = rtrim((string) config('organization.parlan.url'), '/') . '/api/context/rooms';
        $http = fn () => Http::withToken((string) config('organization.parlan.secret'))
            ->withHeaders(['X-Parlan-Origin' => ParlanContext::origin()])->acceptJson()->timeout(30);
        $res = $http()->get($base);
        if (! $res->successful()) {
            $this->warn('Erledigt-Abgleich übersprungen: HTTP ' . $res->status());

            return;
        }
        $registry = app(EntityLinkRegistry::class);
        $states = [];
        $cards = [];
        foreach ((array) ($res->json('refs') ?? []) as $ref) {
            $ref = (string) $ref;
            $alias = explode('/', $ref)[1] ?? '';
            $model = ParlanContext::modelForRef($ref);
            $done = $model === null ? true : $registry->contextDone($alias, $model);
            if ($done !== null) {
                $states[$ref] = $done ? 'done' : 'open';
            }
            if ($model !== null) {
                try {
                    // Kontext-Karte (rooms/0.4): Parlan schreibt sie nur, wenn sie sich geändert hat.
                    $cards[$ref] = ParlanContext::card($alias, $model);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
        $changed = 0;
        foreach (array_chunk($states, 200, true) as $chunk) {
            $r = $http()->post($base . '/state', ['states' => $chunk, 'cards' => array_intersect_key($cards, $chunk)]);
            if (! $r->successful()) {
                $this->warn('Erledigt-Abgleich: HTTP ' . $r->status());

                return;
            }
            $changed += count(array_filter((array) $r->json('result'), fn ($v) => in_array($v, ['rest', 'wake'], true)));
        }
        $this->info('Erledigt-Abgleich: ' . count($states) . " Gespräche geprüft, {$changed} geändert");
    }
}
