<?php

namespace Platform\Organization\Contracts;

interface CostCenterLinkableInterface
{
    public function getCostCenterLinkableId(): int;
    public function getCostCenterLinkableType(): string;
    public function getTeamId(): int;
}


