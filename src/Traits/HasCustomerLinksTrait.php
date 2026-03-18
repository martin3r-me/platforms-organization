<?php

namespace Platform\Organization\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationCustomerLink;

trait HasCustomerLinksTrait
{
    public function customerLinks(): MorphMany
    {
        return $this->morphMany(OrganizationCustomerLink::class, 'linkable');
    }

    public function activeCustomers(?\Carbon\Carbon $at = null): Collection
    {
        $at = $at ?: now();
        return $this->customerLinks()
            ->with('customer')
            ->where(function ($q) use ($at) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $at->toDateString());
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $at->toDateString());
            })
            ->get()
            ->pluck('customer');
    }

    public function attachCustomer($customer, ?string $startDate = null, ?string $endDate = null, ?float $percentage = null, bool $isPrimary = false): void
    {
        $this->customerLinks()->create([
            'customer_id' => is_object($customer) ? $customer->id : $customer,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'percentage' => $percentage,
            'is_primary' => $isPrimary,
        ]);
    }

    public function detachCustomer($customer): void
    {
        $customerId = is_object($customer) ? $customer->id : $customer;
        $this->customerLinks()->where('customer_id', $customerId)->delete();
    }

    public function syncCustomers(array $customerIdToMeta): void
    {
        $this->customerLinks()->delete();
        foreach ($customerIdToMeta as $customerId => $meta) {
            $this->attachCustomer($customerId, $meta['start_date'] ?? null, $meta['end_date'] ?? null, $meta['percentage'] ?? null, $meta['is_primary'] ?? false);
        }
    }
}
