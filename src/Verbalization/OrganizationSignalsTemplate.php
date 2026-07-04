<?php

namespace Platform\Organization\Verbalization;

use Platform\Core\Verbalization\Enums\DataSource;
use Platform\Core\Verbalization\Enums\FactPriority;
use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\Template\NarrativeTemplate;

/**
 * Erzaehlvorlage fuer Signal-Berichte (VSM).
 *
 * Dramaturgie:
 *  1. Identitaet (Signal-Lage fuer Entity X)
 *  2. CORE-Facts (Load, neue Signale, Algedonic)
 *  3. QUALIFYING (Verteilung, Bewegung, Headlines)
 *  4. Datenbasis
 */
class OrganizationSignalsTemplate implements NarrativeTemplate
{
    public function handles(): string
    {
        return 'organization_signals';
    }

    public function renderFactSheet(Subject $subject): string
    {
        $lines = [];
        $lines[] = '## ' . $subject->identity->primaryName;
        $lines[] = '';

        $core = $this->factsByPriority($subject, FactPriority::CORE);
        if (! empty($core)) {
            $lines[] = '### Aktuelle Signal-Lage';
            foreach ($core as $f) {
                $lines[] = '- ' . $f->text;
            }
            $lines[] = '';
        }

        $qualifying = $this->factsByPriority($subject, FactPriority::QUALIFYING);
        if (! empty($qualifying)) {
            $lines[] = '### Bewegung und Verteilung';
            foreach ($qualifying as $f) {
                $lines[] = '- ' . $f->text;
            }
            $lines[] = '';
        }

        $context = $this->factsByPriority($subject, FactPriority::CONTEXT);
        if (! empty($context)) {
            foreach ($context as $f) {
                $lines[] = '- ' . $f->text;
            }
            $lines[] = '';
        }

        $lines[] = '### Daten-Grundlage';
        $lines[] = '- ' . $this->describeFreshness($subject);

        return implode("\n", $lines);
    }

    protected function describeFreshness(Subject $subject): string
    {
        $when = $subject->freshness->asOf->format('d.m.Y H:i');
        return match ($subject->freshness->source) {
            DataSource::LIVE => "Live-Daten (Stand: {$when}).",
            DataSource::SNAPSHOT => "Daten aus Snapshot vom {$when}.",
            DataSource::SNAPSHOT_WITH_LIVE_TOPUP => "Basis: Snapshot vom {$when}, ergaenzt um Live-Bewegungen seitdem.",
        };
    }

    /** @return \Platform\Core\Verbalization\Fact[] */
    protected function factsByPriority(Subject $subject, FactPriority $priority): array
    {
        return array_values(array_filter($subject->facts, fn ($f) => $f->priority === $priority));
    }
}
