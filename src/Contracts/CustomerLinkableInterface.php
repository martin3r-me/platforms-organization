<?php

namespace Platform\Organization\Contracts;

interface CustomerLinkableInterface
{
    public function getCustomerLinkableId(): int;
    public function getCustomerLinkableType(): string;
    public function getTeamId(): int;
}
