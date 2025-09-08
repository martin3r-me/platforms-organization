<?php

namespace Platform\Organization\Contracts;

interface CompanyLinkableInterface
{
    public function getCompanyLinkableId(): int;
    public function getCompanyLinkableType(): string;
    public function getTeamId(): int;
}



