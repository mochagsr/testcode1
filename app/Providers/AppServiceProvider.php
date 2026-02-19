<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Models\InvoicePayment;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\ReceivableLedger;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\SupplierLedger;
use App\Models\SupplierPayment;
use App\Observers\CustomerAuditObserver;
use App\Observers\FinancialModelAuditObserver;
use App\Observers\ProductAuditObserver;
use App\Observers\SalesInvoiceAuditObserver;
use App\Services\AuditLogService;
use App\Services\ApprovalWorkflowService;
use App\Services\AccountingService;
use App\Services\ConfigurationService;
use App\Support\PerformanceMonitor;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register configuration service as singleton
        $this->app->singleton(ConfigurationService::class);

        // Bind audit log service for dependency injection
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(AccountingService::class);
        $this->app->singleton(ApprovalWorkflowService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pagination theme
        Paginator::defaultView('vendor.pagination.pgpos');
        Paginator::defaultSimpleView('vendor.pagination.pgpos-simple');

        // Enable query logging in debug mode (except unit tests)
        if (config('app.debug') && !app()->runningUnitTests()) {
            PerformanceMonitor::enableQueryLogging();
        }

        // Register model observers for audit logging
        $this->registerModelObservers();
    }

    /**
     * Register model observers.
     */
    private function registerModelObservers(): void
    {
        $observers = [
            Product::class => ProductAuditObserver::class,
            Customer::class => CustomerAuditObserver::class,
            SalesInvoice::class => SalesInvoiceAuditObserver::class,
            SalesReturn::class => FinancialModelAuditObserver::class,
            InvoicePayment::class => FinancialModelAuditObserver::class,
            ReceivablePayment::class => FinancialModelAuditObserver::class,
            ReceivableLedger::class => FinancialModelAuditObserver::class,
            OutgoingTransaction::class => FinancialModelAuditObserver::class,
            SupplierPayment::class => FinancialModelAuditObserver::class,
            SupplierLedger::class => FinancialModelAuditObserver::class,
        ];

        foreach ($observers as $model => $observer) {
            $model::observe($observer);
        }
    }
}
