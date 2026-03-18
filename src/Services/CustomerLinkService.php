<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationCustomer;
use Platform\Organization\Models\OrganizationCustomerLink;
use Platform\Organization\Contracts\CustomerLinkableInterface;

class CustomerLinkService
{
    public function findCustomersByName(string $query, int $teamId, int $limit = 20): Collection
    {
        return OrganizationCustomer::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function linkCustomer(CustomerLinkableInterface $linkable, OrganizationCustomer $customer, array $meta = []): void
    {
        OrganizationCustomerLink::create([
            'customer_id' => $customer->id,
            'linkable_type' => $linkable->getCustomerLinkableType(),
            'linkable_id' => $linkable->getCustomerLinkableId(),
            'start_date' => $meta['start_date'] ?? null,
            'end_date' => $meta['end_date'] ?? null,
            'percentage' => $meta['percentage'] ?? null,
            'is_primary' => $meta['is_primary'] ?? false,
            'team_id' => $linkable->getTeamId(),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    public function unlinkCustomer(CustomerLinkableInterface $linkable, OrganizationCustomer $customer): void
    {
        OrganizationCustomerLink::where([
            'customer_id' => $customer->id,
            'linkable_type' => $linkable->getCustomerLinkableType(),
            'linkable_id' => $linkable->getCustomerLinkableId(),
        ])->delete();
    }

    public function getLinkedCustomers(CustomerLinkableInterface $linkable): Collection
    {
        return OrganizationCustomerLink::where([
            'linkable_type' => $linkable->getCustomerLinkableType(),
            'linkable_id' => $linkable->getCustomerLinkableId(),
        ])->with('customer')->get()->pluck('customer');
    }
}
