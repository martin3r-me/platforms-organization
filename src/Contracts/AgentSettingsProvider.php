<?php

namespace Platform\Organization\Contracts;

/**
 * Domaenen-spezifische Agent-Settings (loose, #810): jedes Modul mit einer VSM-Domaene
 * (development, backoffice, …) registriert hier seine Felder, statt sie fest ins Agent-Tab
 * zu verdrahten. AgentSettingsRegistry sammelt sie, das ProfilePanel rendert sie generisch.
 */
interface AgentSettingsProvider
{
    /** VSM-Domaene, fuer die dieser Provider Felder registriert (development|backoffice|helpdesk|assistant|analysis). */
    public function domain(): string;

    /**
     * Deklaratives Feld-Schema. Jedes Feld:
     * [
     *   'key'        => string,                              // eindeutig innerhalb der Domaene
     *   'label'      => string,
     *   'type'       => 'bool'|'int'|'string'|'enum',
     *   'default'    => mixed,
     *   'options'    => array<string,string>|null,            // nur bei type=enum: value => Label
     *   'help'       => string|null,
     *   'validation' => string|array|null,                    // Laravel-Validation-Rule(s)
     *   'storage'    => string,                                // 'column:<spaltenname>' (bestehende Profil-Spalte) oder 'bag' (settings-JSON)
     * ]
     *
     * @return array<int, array{key: string, label: string, type: string, default: mixed, options: array|null, help: string|null, validation: mixed, storage: string}>
     */
    public function fields(): array;
}
