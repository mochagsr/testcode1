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
            __('ui.audit_desc_customer_created', [
                'name' => (string) $customer->name,
                'code' => (string) ($customer->code ?? '-'),
            ])
        );
    }

    public function updated(Customer $customer): void
    {
        $this->logUpdated($customer);
    }

    public function deleted(Customer $customer): void
    {
        $this->logDeleted($customer, __('ui.audit_desc_customer_deleted', [
            'name' => (string) $customer->name,
        ]));
    }
}
