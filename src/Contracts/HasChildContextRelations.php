<?php

namespace Platform\Organization\Contracts;

interface HasChildContextRelations
{
    /**
     * Returns the relation paths whose children should be included
     * in time-entry cascading (e.g. ['tasks', 'projectSlots.tasks']).
     *
     * @return array<string>
     */
    public static function childContextRelations(): array;
}
