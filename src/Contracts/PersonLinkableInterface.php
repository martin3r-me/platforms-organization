<?php

namespace Platform\Organization\Contracts;

interface PersonLinkableInterface
{
    public function getPersonLinkableId(): int;
    public function getPersonLinkableType(): string;
    public function getTeamId(): int;
}
