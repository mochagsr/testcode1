<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Customer;

/**
 * Observer for Customer model.
 */
final class CustomerAuditObserver extends BaseModelAuditObserver
{
    public function created(Customer $customer): void
    {
        $this->logCreated(
            $customer,
            "Customer '{$customer->name}' created with code '{$customer->code}'"
        );
    }

    public function updated(Customer $customer): void
    {
        $this->logUpdated($customer);
    }

    public function deleted(Customer $customer): void
    {
        $this->logDeleted($customer, "Customer '{$customer->name}' deleted");
    }
}
