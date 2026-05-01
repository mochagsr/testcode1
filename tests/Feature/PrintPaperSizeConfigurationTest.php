<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrintPaperSizeConfigurationTest extends TestCase
{
    public function test_print_templates_use_continuous_form_paper_size(): void
    {
        $printTemplates = [
            resource_path('views/delivery_notes/print.blade.php'),
            resource_path('views/delivery_trips/print.blade.php'),
            resource_path('views/order_notes/print.blade.php'),
            resource_path('views/outgoing_transactions/print.blade.php'),
            resource_path('views/photos/print.blade.php'),
            resource_path('views/products/report.blade.php'),
            resource_path('views/receivables/global_print.blade.php'),
            resource_path('views/receivables/print_customer_bill.blade.php'),
            resource_path('views/receivables/semester_print.blade.php'),
            resource_path('views/receivable_payments/print.blade.php'),
            resource_path('views/reports/pdf.blade.php'),
            resource_path('views/reports/print.blade.php'),
            resource_path('views/sales_invoices/print.blade.php'),
            resource_path('views/sales_returns/print.blade.php'),
            resource_path('views/school_bulk_transactions/print.blade.php'),
            resource_path('views/supplier_payables/print.blade.php'),
            resource_path('views/supplier_payables/report.blade.php'),
            resource_path('views/supplier_stock_cards/report.blade.php'),
        ];

        foreach ($printTemplates as $template) {
            $this->assertFileExists($template);
            $this->assertStringContainsString(
                "@include('partials.print.paper_size')",
                (string) file_get_contents($template),
                "Print template {$template} must use the shared 9.5 x 11 paper size."
            );
        }
    }

    public function test_pdf_exports_do_not_use_a4_paper(): void
    {
        $controllerFiles = glob(app_path('Http/Controllers/*.php')) ?: [];

        foreach ($controllerFiles as $controllerFile) {
            $contents = (string) file_get_contents($controllerFile);

            $this->assertStringNotContainsString("setPaper('a4'", $contents, "{$controllerFile} still exports PDF as A4.");
            $this->assertStringNotContainsString('setPaper("a4"', $contents, "{$controllerFile} still exports PDF as A4.");
        }
    }

    public function test_dompdf_paper_size_matches_nine_and_half_by_eleven_inches(): void
    {
        $this->assertSame([0, 0, 684, 792], \App\Support\PrintPaperSize::continuousForm95x11());
    }
}
