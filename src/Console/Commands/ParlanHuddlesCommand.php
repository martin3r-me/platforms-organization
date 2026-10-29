<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Platform\Organization\Support\ParlanContext;

/**
 * Kalender-Spiegel: holt die geplanten Huddles der eigenen Domäne aus Parlan
 * (GET /api/context/huddles, Integrations-Geheimnis wie beim Rückweg) und hält sie über den
 * user-connectors-Kontrakt CalendarMirror als Termin im M365-Kalender des Organisators — mit den
 * Menschen des Gesprächs als Eingeladene. TRAAN bleibt die Quelle; Outlook ist nur Kopie.
 * Lose gekoppelt: ohne user-connectors oder ohne Parlan-Konfiguration ein No-op.
 */
class ParlanHuddlesCommand extends Command
{
    protected $signature = 'organization:parlan-huddles {--days=60 : Vorlauf in Tagen (1–120)} {--dry : nur zeigen, nichts schreiben} {--only=* : nur diese Organisatoren (E-Mail), z. B. für einen Pilot}';

    protected $description = 'Geplante Parlan-Huddles in die M365-Kalender der Organisatoren spiegeln';

    public function handle(): int
    {
        $mirrorClass = \Platform\UserConnectors\Services\CalendarMirror::class;
        if (! ParlanContext::enabled() || ! class_exists($mirrorClass)) {
            $this->line('Parlan oder user-connectors nicht verfügbar — nichts zu tun.');

            return self::SUCCESS;
        }
        // Wer gespiegelt wird: --only, sonst (außer bei --dry) der Schalter (all | Pilot-Liste | leer = aus).
        $only = array_map('strtolower', (array) $this->option('only'));
        if ($only === [] && ! $this->option('dry')) {
            $gate = trim((string) config('organization.parlan.huddle_mirror'));
            if ($gate === '') {
                $this->line('Huddle-Spiegel aus (PARLAN_HUDDLE_MIRROR leer).');

                return self::SUCCESS;
            }
            $only = $gate === 'all' ? [] : array_values(array_filter(array_map(fn ($e) => strtolower(trim($e)), explode(',', $gate))));
        }
        $res = Http::withToken((string) config('organization.parlan.secret'))
            ->withHeaders(['X-Parlan-Origin' => ParlanContext::origin()])
            ->acceptJson()->timeout(30)
            ->get(rtrim((string) config('organization.parlan.url'), '/') . '/api/context/huddles', ['days' => (int) $this->option('days')]);
        if (! $res->successful()) {
            $this->error('Abholen fehlgeschlagen: HTTP ' . $res->status());

            return self::FAILURE;
        }

        $userClass = config('auth.providers.users.model');
        $mirror = app($mirrorClass);
        $tally = [];
        $unknown = [];
        foreach ((array) ($res->json('plans') ?? []) as $p) {
            if ($only !== [] && ! in_array(strtolower((string) ($p['organizer'] ?? '')), $only, true)) {
                continue;
            }
            $organizer = $userClass::query()->where('email', (string) ($p['organizer'] ?? ''))->first();
            if ($organizer === null) {
                $unknown[] = (string) ($p['organizer'] ?? '?');
                continue;
            }
            $event = [
                'title' => (string) $p['title'],
                'start' => (string) $p['start'],
                'end' => (string) $p['end'],
                'canceled' => (bool) ($p['canceled'] ?? false),
                'attendees' => array_values(array_map(static fn ($a) => ['email' => (string) $a['email'], 'name' => (string) ($a['name'] ?? '')],
                    (array) ($p['attendees'] ?? []))),
                'location' => 'Parlan' . (($p['room'] ?? '') !== '' ? ' · ' . $p['room'] : ''),
                'description' => '<p>Huddle in Parlan' . (($p['room'] ?? '') !== '' ? ' im Gespräch ' . e((string) $p['room']) : '') . '.</p>'
                    . '<p><a href="' . e((string) $p['url']) . '">In Parlan öffnen und beitreten</a></p>',
            ];
            if ($this->option('dry')) {
                $status = $mirror->canMirror($organizer) ? 'dry' : 'no_connection';
                $this->line(sprintf('  %s  %-32s  %s → %d Eingeladene%s  [%s]', $event['start'], mb_strimwidth($event['title'], 0, 32, '…'),
                    $organizer->email, count($event['attendees']), $event['canceled'] ? ' · abgesagt' : '', $status));
            } else {
                $status = $mirror->sync('parlan-huddle', (string) $p['plan'], $organizer, $event);
            }
            $tally[$status] = ($tally[$status] ?? 0) + 1;
        }

        ksort($tally);
        $this->info('Huddle-Spiegel: ' . ($tally === [] ? 'keine Termine' : implode(' · ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($tally), $tally)))
            . ($unknown ? ' · ohne Org-User: ' . implode(', ', array_unique($unknown)) : ''));

        return self::SUCCESS;
    }
}
