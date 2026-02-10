<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditLogPageController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPageController;
use App\Http\Controllers\CustomerLevelController;
use App\Http\Controllers\CustomerLevelPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNotePageController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemCategoryPageController;
use App\Http\Controllers\OrderNotePageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPageController;
use App\Http\Controllers\ReceivablePageController;
use App\Http\Controllers\ReceivablePaymentPageController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SalesInvoicePageController;
use App\Http\Controllers\SalesReturnPageController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('api')->name('api.')->group(function (): void {
    Route::apiResource('item-categories', ItemCategoryController::class);
    Route::apiResource('customer-levels', CustomerLevelController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('customers', CustomerController::class);
});

Route::middleware(['auth', 'prefs'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sales-invoices', [SalesInvoicePageController::class, 'index'])->name('sales-invoices.index');
    Route::get('/sales-invoices/create', [SalesInvoicePageController::class, 'create'])->name('sales-invoices.create');
    Route::post('/sales-invoices', [SalesInvoicePageController::class, 'store'])->middleware('finance.unlocked')->name('sales-invoices.store');
    Route::get('/sales-invoices/{salesInvoice}', [SalesInvoicePageController::class, 'show'])->name('sales-invoices.show');
    Route::get('/sales-invoices/{salesInvoice}/print', [SalesInvoicePageController::class, 'print'])->name('sales-invoices.print');
    Route::get('/sales-invoices/{salesInvoice}/pdf', [SalesInvoicePageController::class, 'exportPdf'])->name('sales-invoices.export.pdf');
    Route::get('/sales-invoices/{salesInvoice}/excel', [SalesInvoicePageController::class, 'exportExcel'])->name('sales-invoices.export.excel');

    Route::get('/sales-returns', [SalesReturnPageController::class, 'index'])->name('sales-returns.index');
    Route::get('/sales-returns/create', [SalesReturnPageController::class, 'create'])->name('sales-returns.create');
    Route::post('/sales-returns', [SalesReturnPageController::class, 'store'])->middleware('finance.unlocked')->name('sales-returns.store');
    Route::get('/sales-returns/{salesReturn}', [SalesReturnPageController::class, 'show'])->name('sales-returns.show');
    Route::get('/sales-returns/{salesReturn}/print', [SalesReturnPageController::class, 'print'])->name('sales-returns.print');
    Route::get('/sales-returns/{salesReturn}/pdf', [SalesReturnPageController::class, 'exportPdf'])->name('sales-returns.export.pdf');
    Route::get('/sales-returns/{salesReturn}/excel', [SalesReturnPageController::class, 'exportExcel'])->name('sales-returns.export.excel');

    Route::get('/delivery-notes', [DeliveryNotePageController::class, 'index'])->name('delivery-notes.index');
    Route::get('/delivery-notes/create', [DeliveryNotePageController::class, 'create'])->name('delivery-notes.create');
    Route::post('/delivery-notes', [DeliveryNotePageController::class, 'store'])->name('delivery-notes.store');
    Route::get('/delivery-notes/{deliveryNote}', [DeliveryNotePageController::class, 'show'])->name('delivery-notes.show');
    Route::get('/delivery-notes/{deliveryNote}/print', [DeliveryNotePageController::class, 'print'])->name('delivery-notes.print');
    Route::get('/delivery-notes/{deliveryNote}/pdf', [DeliveryNotePageController::class, 'exportPdf'])->name('delivery-notes.export.pdf');
    Route::get('/delivery-notes/{deliveryNote}/excel', [DeliveryNotePageController::class, 'exportExcel'])->name('delivery-notes.export.excel');

    Route::get('/order-notes', [OrderNotePageController::class, 'index'])->name('order-notes.index');
    Route::get('/order-notes/create', [OrderNotePageController::class, 'create'])->name('order-notes.create');
    Route::post('/order-notes', [OrderNotePageController::class, 'store'])->name('order-notes.store');
    Route::get('/order-notes/{orderNote}', [OrderNotePageController::class, 'show'])->name('order-notes.show');
    Route::get('/order-notes/{orderNote}/print', [OrderNotePageController::class, 'print'])->name('order-notes.print');
    Route::get('/order-notes/{orderNote}/pdf', [OrderNotePageController::class, 'exportPdf'])->name('order-notes.export.pdf');
    Route::get('/order-notes/{orderNote}/excel', [OrderNotePageController::class, 'exportExcel'])->name('order-notes.export.excel');

    Route::get('/receivables', [ReceivablePageController::class, 'index'])->name('receivables.index');
    Route::post('/receivables/pay/{salesInvoice}', [ReceivablePageController::class, 'pay'])
        ->middleware('finance.unlocked')
        ->name('receivables.pay');
    Route::get('/receivable-payments', [ReceivablePaymentPageController::class, 'index'])->name('receivable-payments.index');
    Route::get('/receivable-payments/create', [ReceivablePaymentPageController::class, 'create'])->name('receivable-payments.create');
    Route::post('/receivable-payments', [ReceivablePaymentPageController::class, 'store'])
        ->middleware('finance.unlocked')
        ->name('receivable-payments.store');
    Route::get('/receivable-payments/{receivablePayment}', [ReceivablePaymentPageController::class, 'show'])->name('receivable-payments.show');
    Route::get('/receivable-payments/{receivablePayment}/print', [ReceivablePaymentPageController::class, 'print'])->name('receivable-payments.print');
    Route::get('/receivable-payments/{receivablePayment}/pdf', [ReceivablePaymentPageController::class, 'exportPdf'])->name('receivable-payments.export.pdf');

    Route::get('/reports', [ReportExportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{dataset}/csv', [ReportExportController::class, 'exportCsv'])->name('reports.export.csv');
    Route::get('/reports/{dataset}/pdf', [ReportExportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('/reports/{dataset}/print', [ReportExportController::class, 'print'])->name('reports.print');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::middleware('admin')->group(function (): void {
        Route::get('/item-categories', [ItemCategoryPageController::class, 'index'])->name('item-categories.index');
        Route::get('/item-categories/create', [ItemCategoryPageController::class, 'create'])->name('item-categories.create');
        Route::post('/item-categories', [ItemCategoryPageController::class, 'store'])->name('item-categories.store');
        Route::get('/item-categories/{itemCategory}/edit', [ItemCategoryPageController::class, 'edit'])->name('item-categories.edit');
        Route::put('/item-categories/{itemCategory}', [ItemCategoryPageController::class, 'update'])->name('item-categories.update');
        Route::delete('/item-categories/{itemCategory}', [ItemCategoryPageController::class, 'destroy'])->name('item-categories.destroy');

        Route::get('/products', [ProductPageController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductPageController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductPageController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductPageController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductPageController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductPageController::class, 'destroy'])->name('products.destroy');

        Route::get('/customer-levels-web', [CustomerLevelPageController::class, 'index'])->name('customer-levels-web.index');
        Route::get('/customer-levels-web/create', [CustomerLevelPageController::class, 'create'])->name('customer-levels-web.create');
        Route::post('/customer-levels-web', [CustomerLevelPageController::class, 'store'])->name('customer-levels-web.store');
        Route::get('/customer-levels-web/{customerLevel}/edit', [CustomerLevelPageController::class, 'edit'])->name('customer-levels-web.edit');
        Route::put('/customer-levels-web/{customerLevel}', [CustomerLevelPageController::class, 'update'])->name('customer-levels-web.update');
        Route::delete('/customer-levels-web/{customerLevel}', [CustomerLevelPageController::class, 'destroy'])->name('customer-levels-web.destroy');

        Route::get('/customers-web', [CustomerPageController::class, 'index'])->name('customers-web.index');
        Route::get('/customers-web/create', [CustomerPageController::class, 'create'])->name('customers-web.create');
        Route::post('/customers-web', [CustomerPageController::class, 'store'])->name('customers-web.store');
        Route::get('/customers-web/{customer}/edit', [CustomerPageController::class, 'edit'])->name('customers-web.edit');
        Route::put('/customers-web/{customer}', [CustomerPageController::class, 'update'])->name('customers-web.update');
        Route::delete('/customers-web/{customer}', [CustomerPageController::class, 'destroy'])->name('customers-web.destroy');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::get('/audit-logs', [AuditLogPageController::class, 'index'])->name('audit-logs.index');
    });
});
