<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationEnvironmentSource;

class SeedEnvironmentSourcesCommand extends Command
{
    protected $signature = 'organization:seed-environment-sources {--team= : Team-ID}';

    protected $description = 'Seed curated environment sources (RSS feeds) for a team';

    public function handle(): int
    {
        $teamId = $this->option('team');

        if (! $teamId) {
            $team = Team::first();
            if (! $team) {
                $this->error('No team found. Provide --team=ID.');

                return self::FAILURE;
            }
            $teamId = $team->id;
        }

        $existing = OrganizationEnvironmentSource::forTeam($teamId)->count();
        if ($existing > 0) {
            if (! $this->confirm("Team {$teamId} already has {$existing} sources. Add anyway?")) {
                return self::SUCCESS;
            }
        }

        $sources = $this->getSourceDefinitions();
        $created = 0;

        foreach ($sources as $def) {
            // Skip if source with same name already exists for this team
            if (OrganizationEnvironmentSource::forTeam($teamId)->where('name', $def['name'])->exists()) {
                $this->line("  Skip (exists): {$def['name']}");

                continue;
            }

            OrganizationEnvironmentSource::create([
                'team_id' => $teamId,
                'name' => $def['name'],
                'source_type' => 'rss',
                'category' => $def['category'],
                'cluster' => $def['cluster'],
                'pull_interval_hours' => $def['interval'],
                'is_active' => true,
                'config' => [
                    'url' => $def['url'],
                    'extraction_prompt' => $def['prompt'] ?? null,
                ],
            ]);

            $created++;
            $this->line("  Created: {$def['name']} [{$def['cluster']}]");
        }

        $this->info("Seeded {$created} environment sources for team {$teamId}.");

        return self::SUCCESS;
    }

    protected function getSourceDefinitions(): array
    {
        return [
            // ── DACH / Deutschland ──
            [
                'name' => 'Destatis',
                'cluster' => 'dach',
                'category' => 'macro',
                'interval' => 24,
                'url' => 'https://www.destatis.de/SiteGlobals/Functions/RSSFeed/DE/Presse/RSS.xml',
                'prompt' => 'Fokus auf BIP, Inflation, Erwerbstätigkeit, Löhne. Relevanz für mittelständische Unternehmen in Deutschland.',
            ],
            [
                'name' => 'Bundesagentur für Arbeit',
                'cluster' => 'dach',
                'category' => 'talent',
                'interval' => 24,
                'url' => 'https://statistik.arbeitsagentur.de/rss',
                'prompt' => 'Fokus auf Arbeitslosigkeit, Fachkräftemangel, Kurzarbeit.',
            ],
            [
                'name' => 'ifo Institut',
                'cluster' => 'dach',
                'category' => 'macro',
                'interval' => 24,
                'url' => 'https://www.ifo.de/rss.xml',
                'prompt' => 'Geschäftsklimaindex, Konjunkturprognosen Deutschland.',
            ],
            [
                'name' => 'Bundesbank',
                'cluster' => 'dach',
                'category' => 'macro',
                'interval' => 168,
                'url' => 'https://www.bundesbank.de/rss/press.xml',
                'prompt' => 'Geldpolitik, Finanzstabilität, Monatsbericht.',
            ],
            [
                'name' => 'Tagesschau',
                'cluster' => 'dach',
                'category' => 'industry',
                'interval' => 24,
                'url' => 'https://www.tagesschau.de/xml/rss2',
                'prompt' => 'Politik und Inland. Faktische Berichterstattung, kein Meinungsjournalismus.',
            ],
            [
                'name' => 'Bundestag',
                'cluster' => 'dach',
                'category' => 'regulation',
                'interval' => 168,
                'url' => 'https://www.bundestag.de/rss/gesetzgebung',
                'prompt' => 'Gesetze, Ausschussergebnisse. Relevanz für Unternehmensregulierung.',
            ],

            // ── Europa ──
            [
                'name' => 'Eurostat',
                'cluster' => 'europa',
                'category' => 'macro',
                'interval' => 168,
                'url' => 'https://ec.europa.eu/eurostat/rss/news',
                'prompt' => 'Makrodaten EU-weit.',
            ],
            [
                'name' => 'EU-Kommission',
                'cluster' => 'europa',
                'category' => 'regulation',
                'interval' => 168,
                'url' => 'https://ec.europa.eu/commission/rss',
                'prompt' => 'Regulierung, Digitalpolitik, AI Act.',
            ],
            [
                'name' => 'EZB',
                'cluster' => 'europa',
                'category' => 'macro',
                'interval' => 168,
                'url' => 'https://www.ecb.europa.eu/rss/press.html',
                'prompt' => 'Zinsentscheidungen, Geldpolitik.',
            ],
            [
                'name' => 'EUR-Lex',
                'cluster' => 'europa',
                'category' => 'regulation',
                'interval' => 168,
                'url' => 'https://eur-lex.europa.eu/rss',
                'prompt' => 'Neue EU-Gesetze und Verordnungen.',
            ],

            // ── Global ──
            [
                'name' => 'OECD',
                'cluster' => 'global',
                'category' => 'macro',
                'interval' => 168,
                'url' => 'https://www.oecd.org/newsroom/rss.xml',
                'prompt' => 'Wirtschaft, Arbeit, Gesellschaft global.',
            ],
            [
                'name' => 'IMF Blog',
                'cluster' => 'global',
                'category' => 'macro',
                'interval' => 168,
                'url' => 'https://www.imf.org/en/News/rss',
                'prompt' => 'Globale Konjunktur, Finanzstabilität.',
            ],
            [
                'name' => 'Foreign Affairs',
                'cluster' => 'global',
                'category' => 'geopolitics',
                'interval' => 24,
                'url' => 'https://www.foreignaffairs.com/rss.xml',
                'prompt' => 'Geopolitik, höchste Analysequalität.',
            ],
            [
                'name' => 'Council on Foreign Relations',
                'cluster' => 'global',
                'category' => 'geopolitics',
                'interval' => 168,
                'url' => 'https://www.cfr.org/rss/all',
                'prompt' => 'Geopolitik, US/China/EU.',
            ],

            // ── Technologie & KI ──
            [
                'name' => 'MIT Technology Review',
                'cluster' => 'tech_ai',
                'category' => 'technology',
                'interval' => 24,
                'url' => 'https://feeds.feedburner.com/mit-technology-review',
                'prompt' => 'KI, Tech-Trends, gesellschaftliche Implikationen.',
            ],
            [
                'name' => 'Benedict Evans',
                'cluster' => 'tech_ai',
                'category' => 'technology',
                'interval' => 168,
                'url' => 'https://www.ben-evans.com/benedictevans/rss.xml',
                'prompt' => 'Plattformstrategie, Tech-Strukturwandel.',
            ],
            [
                'name' => 'The Pragmatic Engineer',
                'cluster' => 'tech_ai',
                'category' => 'technology',
                'interval' => 168,
                'url' => 'https://newsletter.pragmaticengineer.com/feed',
                'prompt' => 'Tech-Industrie, Engineering-Trends.',
            ],
            [
                'name' => 'Import AI (Jack Clark)',
                'cluster' => 'tech_ai',
                'category' => 'technology',
                'interval' => 168,
                'url' => 'https://jack-clark.net/feed',
                'prompt' => 'KI-Forschung wöchentlich, sehr kompakt.',
            ],

            // ── Strategische Tiefenanalyse ──
            [
                'name' => 'McKinsey Global Institute',
                'cluster' => 'strategic',
                'category' => 'industry',
                'interval' => 168,
                'url' => 'https://www.mckinsey.com/mgi/rss',
                'prompt' => 'Strukturelle Wirtschaftstrends, Sektorreports.',
            ],
            [
                'name' => 'Kiel Institut (IfW)',
                'cluster' => 'strategic',
                'category' => 'macro',
                'interval' => 168,
                'url' => 'https://www.ifw-kiel.de/rss',
                'prompt' => 'Außenhandel, Konjunktur, Deutschland-Fokus.',
            ],
            [
                'name' => 'Bertelsmann Stiftung',
                'cluster' => 'strategic',
                'category' => 'industry',
                'interval' => 168,
                'url' => 'https://www.bertelsmann-stiftung.de/de/rss',
                'prompt' => 'Demografie, Gesellschaft, Arbeit.',
            ],
            [
                'name' => 'World Economic Forum',
                'cluster' => 'strategic',
                'category' => 'geopolitics',
                'interval' => 168,
                'url' => 'https://www.weforum.org/rss.xml',
                'prompt' => 'Globale Risiken, Zukunftsthemen.',
            ],

            // ── Gesellschaft & Konsum ──
            [
                'name' => 'HDE (Handelsverband)',
                'cluster' => 'society',
                'category' => 'market',
                'interval' => 168,
                'url' => 'https://einzelhandel.de/rss',
                'prompt' => 'Einzelhandel, Konsumtrends Deutschland.',
            ],
            [
                'name' => 'foodservice.de',
                'cluster' => 'society',
                'category' => 'gastronomy',
                'interval' => 24,
                'url' => 'https://www.foodservice.de/rss',
                'prompt' => 'Gastronomie- und Cateringbranche. Direkt relevant für Food/Event-Ventures.',
            ],
        ];
    }
}
