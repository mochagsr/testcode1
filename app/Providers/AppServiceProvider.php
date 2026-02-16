<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Observers\CustomerAuditObserver;
use App\Observers\ProductAuditObserver;
use App\Observers\SalesInvoiceAuditObserver;
use App\Services\AuditLogService;
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
        ];

        foreach ($observers as $model => $observer) {
            $model::observe($observer);
        }
    }
}
