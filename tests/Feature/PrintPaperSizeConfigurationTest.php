<?php

namespace Tests\Feature;

use App\Support\PrintPaperSize;
use Tests\TestCase;

class PrintPaperSizeConfigurationTest extends TestCase
{
    public function test_transaction_print_templates_use_nine_and_half_by_eleven_paper(): void
    {
        $printTemplates = [
            resource_path('views/delivery_notes/print.blade.php'),
            resource_path('views/delivery_trips/print.blade.php'),
            resource_path('views/order_notes/print.blade.php'),
            resource_path('views/outgoing_transactions/print.blade.php'),
            resource_path('views/sales_invoices/print.blade.php'),
            resource_path('views/sales_returns/print.blade.php'),
            resource_path('views/school_bulk_transactions/print.blade.php'),
        ];

        foreach ($printTemplates as $template) {
            $this->assertFileExists($template);
            $this->assertStringContainsString(
                "@include('partials.print.paper_transaction')",
                (string) file_get_contents($template),
                "Transaction print template {$template} must use 9.5 x 11 paper."
            );
        }
    }

    public function test_receipt_print_templates_use_nine_and_half_by_five_and_half_paper(): void
    {
        $printTemplates = [
            resource_path('views/receivable_payments/print.blade.php'),
            resource_path('views/supplier_payables/print.blade.php'),
        ];

        foreach ($printTemplates as $template) {
            $this->assertFileExists($template);
            $this->assertStringContainsString(
                "@include('partials.print.paper_receipt')",
                (string) file_get_contents($template),
                "Receipt print template {$template} must use 9.5 x 5.5 paper."
            );
        }
    }

    public function test_non_transaction_print_templates_use_a4_paper(): void
    {
        $printTemplates = [
            resource_path('views/photos/print.blade.php'),
            resource_path('views/products/report.blade.php'),
            resource_path('views/receivables/global_print.blade.php'),
            resource_path('views/receivables/print_customer_bill.blade.php'),
            resource_path('views/receivables/semester_print.blade.php'),
            resource_path('views/reports/pdf.blade.php'),
            resource_path('views/reports/print.blade.php'),
            resource_path('views/supplier_payables/report.blade.php'),
            resource_path('views/supplier_stock_cards/report.blade.php'),
        ];

        foreach ($printTemplates as $template) {
            $this->assertFileExists($template);
            $this->assertStringContainsString(
                "@include('partials.print.paper_a4')",
                (string) file_get_contents($template),
                "Non-transaction print template {$template} must use A4 paper."
            );
        }
    }

    public function test_pdf_exports_use_expected_paper_size_by_document_type(): void
    {
        $transactionControllerFiles = [
            app_path('Http/Controllers/DeliveryNotePageController.php'),
            app_path('Http/Controllers/DeliveryTripPageController.php'),
            app_path('Http/Controllers/OrderNotePageController.php'),
            app_path('Http/Controllers/OutgoingTransactionPageController.php'),
            app_path('Http/Controllers/SalesInvoicePageController.php'),
            app_path('Http/Controllers/SalesReturnPageController.php'),
            app_path('Http/Controllers/SchoolBulkTransactionPageController.php'),
        ];

        foreach ($transactionControllerFiles as $controllerFile) {
            $this->assertStringContainsString('continuousForm95x11()', (string) file_get_contents($controllerFile));
        }

        $receiptControllerFiles = [
            app_path('Http/Controllers/ReceivablePaymentPageController.php'),
            app_path('Http/Controllers/SupplierPayablePageController.php'),
        ];

        foreach ($receiptControllerFiles as $controllerFile) {
            $this->assertStringContainsString('continuousForm95x55()', (string) file_get_contents($controllerFile));
        }

        $a4ControllerFiles = [
            app_path('Http/Controllers/ProductPageController.php'),
            app_path('Http/Controllers/ReceivablePageController.php'),
            app_path('Http/Controllers/ReportExportController.php'),
            app_path('Http/Controllers/SupplierPayablePageController.php'),
            app_path('Http/Controllers/SupplierStockCardPageController.php'),
        ];

        foreach ($a4ControllerFiles as $controllerFile) {
            $this->assertStringContainsString("setPaper('a4'", (string) file_get_contents($controllerFile));
        }
    }

    public function test_dompdf_paper_sizes_match_expected_inches(): void
    {
        $this->assertSame([0, 0, 684, 792], PrintPaperSize::continuousForm95x11());
        $this->assertSame([0, 0, 684, 396], PrintPaperSize::continuousForm95x55());
    }
}
