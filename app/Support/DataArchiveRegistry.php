<?php

declare(strict_types=1);

namespace App\Support;

final class DataArchiveRegistry
{
    /**
     * @return list<string>
     */
    public static function systemDatasetKeys(): array
    {
        return [
            'audit_logs',
            'report_export_tasks',
            'integrity_check_logs',
            'performance_probe_logs',
            'restore_drill_logs',
            'failed_jobs',
            'job_batches',
        ];
    }

    /**
     * @return array<string, array{
     *     label:string,
     *     basis:string,
     *     scope_modes?:list<string>,
     *     purge_allowed:bool,
     *     purge_mode:string,
     *     financial:bool,
     *     tables:array<int, array{
     *         table:string,
     *         date_column?:string,
     *         date_kind?:string,
     *         foreign_key?:string,
     *         semester_column?:string
     *     }>
     * }>
     */
    public static function definitions(): array
    {
        return [
            'audit_logs' => [
                'label' => 'Audit Log',
                'basis' => 'month',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'standard',
                'financial' => false,
                'tables' => [
                    ['table' => 'audit_logs', 'date_column' => 'created_at'],
                ],
            ],
            'report_export_tasks' => [
                'label' => 'Task Export Laporan',
                'basis' => 'month',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'standard',
                'financial' => false,
                'tables' => [
                    ['table' => 'report_export_tasks', 'date_column' => 'created_at'],
                ],
            ],
            'integrity_check_logs' => [
                'label' => 'Integrity Check Logs',
                'basis' => 'month',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'standard',
                'financial' => false,
                'tables' => [
                    ['table' => 'integrity_check_logs', 'date_column' => 'checked_at'],
                ],
            ],
            'performance_probe_logs' => [
                'label' => 'Performance Probe Logs',
                'basis' => 'month',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'standard',
                'financial' => false,
                'tables' => [
                    ['table' => 'performance_probe_logs', 'date_column' => 'probed_at'],
                ],
            ],
            'restore_drill_logs' => [
                'label' => 'Restore Drill Logs',
                'basis' => 'month',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'standard',
                'financial' => false,
                'tables' => [
                    ['table' => 'restore_drill_logs', 'date_column' => 'tested_at'],
                ],
            ],
            'failed_jobs' => [
                'label' => 'Failed Jobs',
                'basis' => 'month',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'standard',
                'financial' => false,
                'tables' => [
                    ['table' => 'failed_jobs', 'date_column' => 'failed_at'],
                ],
            ],
            'job_batches' => [
                'label' => 'Job Batches',
                'basis' => 'month',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'standard',
                'financial' => false,
                'tables' => [
                    ['table' => 'job_batches', 'date_column' => 'created_at', 'date_kind' => 'unix'],
                ],
            ],
            'products' => [
                'label' => 'Daftar Barang',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => false,
                'purge_mode' => 'locked',
                'financial' => false,
                'tables' => [
                    ['table' => 'products', 'date_column' => 'created_at'],
                ],
            ],
            'sales_invoices' => [
                'label' => 'Faktur Penjualan',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'financial_guarded',
                'financial' => true,
                'tables' => [
                    ['table' => 'sales_invoices', 'date_column' => 'invoice_date', 'semester_column' => 'semester_period'],
                    ['table' => 'sales_invoice_items', 'foreign_key' => 'sales_invoice_id'],
                    ['table' => 'invoice_payments', 'foreign_key' => 'sales_invoice_id'],
                    ['table' => 'receivable_ledgers', 'foreign_key' => 'sales_invoice_id'],
                ],
            ],
            'sales_returns' => [
                'label' => 'Retur Penjualan',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'financial_guarded',
                'financial' => true,
                'tables' => [
                    ['table' => 'sales_returns', 'date_column' => 'return_date', 'semester_column' => 'semester_period'],
                    ['table' => 'sales_return_items', 'foreign_key' => 'sales_return_id'],
                ],
            ],
            'delivery_notes' => [
                'label' => 'Surat Jalan',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'financial_guarded',
                'financial' => true,
                'tables' => [
                    ['table' => 'delivery_notes', 'date_column' => 'note_date'],
                    ['table' => 'delivery_note_items', 'foreign_key' => 'delivery_note_id'],
                ],
            ],
            'order_notes' => [
                'label' => 'Surat Pesanan',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'financial_guarded',
                'financial' => true,
                'tables' => [
                    ['table' => 'order_notes', 'date_column' => 'note_date'],
                    ['table' => 'order_note_items', 'foreign_key' => 'order_note_id'],
                ],
            ],
            'outgoing_transactions' => [
                'label' => 'Tanda Terima Barang',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'financial_guarded',
                'financial' => true,
                'tables' => [
                    ['table' => 'outgoing_transactions', 'date_column' => 'transaction_date', 'semester_column' => 'semester_period'],
                    ['table' => 'outgoing_transaction_items', 'foreign_key' => 'outgoing_transaction_id'],
                    ['table' => 'supplier_ledgers', 'foreign_key' => 'outgoing_transaction_id'],
                ],
            ],
            'receivable_ledgers' => [
                'label' => 'Ledger Piutang',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => false,
                'purge_mode' => 'locked',
                'financial' => true,
                'tables' => [
                    ['table' => 'receivable_ledgers', 'date_column' => 'entry_date', 'semester_column' => 'period_code'],
                ],
            ],
            'receivable_payments' => [
                'label' => 'Pembayaran Piutang',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'financial_guarded',
                'financial' => true,
                'tables' => [
                    ['table' => 'receivable_payments', 'date_column' => 'payment_date'],
                ],
            ],
            'supplier_ledgers' => [
                'label' => 'Ledger Hutang Supplier',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => false,
                'purge_mode' => 'locked',
                'financial' => true,
                'tables' => [
                    ['table' => 'supplier_ledgers', 'date_column' => 'entry_date', 'semester_column' => 'period_code'],
                ],
            ],
            'supplier_payments' => [
                'label' => 'Pembayaran Hutang Supplier',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'financial_guarded',
                'financial' => true,
                'tables' => [
                    ['table' => 'supplier_payments', 'date_column' => 'payment_date'],
                    ['table' => 'supplier_ledgers', 'foreign_key' => 'supplier_payment_id'],
                ],
            ],
            'school_bulk_transactions' => [
                'label' => 'Transaksi Sebar Sekolah',
                'basis' => 'year',
                'scope_modes' => ['year', 'semester'],
                'purge_allowed' => true,
                'purge_mode' => 'financial_guarded',
                'financial' => true,
                'tables' => [
                    ['table' => 'school_bulk_transactions', 'date_column' => 'transaction_date'],
                    ['table' => 'school_bulk_transaction_locations', 'foreign_key' => 'school_bulk_transaction_id'],
                    ['table' => 'school_bulk_transaction_items', 'foreign_key' => 'school_bulk_transaction_id'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     label:string,
     *     basis:string,
     *     scope_modes?:list<string>,
     *     purge_allowed:bool,
     *     purge_mode:string,
     *     financial:bool,
     *     tables:array<int, array{
     *         table:string,
     *         date_column?:string,
     *         date_kind?:string,
     *         foreign_key?:string,
     *         semester_column?:string
     *     }>
     * }>
     */
    public static function businessDefinitions(): array
    {
        return array_diff_key(self::definitions(), array_flip(self::systemDatasetKeys()));
    }

    /**
     * @return array<string, array{
     *     label:string,
     *     basis:string,
     *     scope_modes?:list<string>,
     *     purge_allowed:bool,
     *     purge_mode:string,
     *     financial:bool,
     *     tables:array<int, array{
     *         table:string,
     *         date_column?:string,
     *         date_kind?:string,
     *         foreign_key?:string,
     *         semester_column?:string
     *     }>
     * }>
     */
    public static function systemDefinitions(): array
    {
        return array_intersect_key(self::definitions(), array_flip(self::systemDatasetKeys()));
    }

    /**
     * @return array<string, array{label:string, days:int}>
     */
    public static function automaticCleanupRules(): array
    {
        return [
            'audit_logs' => [
                'label' => 'Audit Log',
                'days' => 90,
            ],
            'report_export_tasks' => [
                'label' => 'Task Export Laporan',
                'days' => 180,
            ],
            'integrity_check_logs' => [
                'label' => 'Integrity Check Logs',
                'days' => 180,
            ],
            'performance_probe_logs' => [
                'label' => 'Performance Probe Logs',
                'days' => 90,
            ],
            'restore_drill_logs' => [
                'label' => 'Restore Drill Logs',
                'days' => 180,
            ],
            'failed_jobs' => [
                'label' => 'Failed Jobs',
                'days' => 30,
            ],
            'job_batches' => [
                'label' => 'Job Batches',
                'days' => 30,
            ],
        ];
    }

    /**
     * @param  list<string>  $requested
     * @return array<string, array{
     *     label:string,
     *     basis:string,
     *     purge_allowed:bool,
     *     financial:bool,
     *     tables:array<int, array{
     *         table:string,
     *         date_column?:string,
     *         foreign_key?:string
     *     }>
     * }>
     */
    public static function resolve(array $requested): array
    {
        $definitions = self::definitions();
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $key): string => strtolower(trim((string) $key)),
            $requested
        ))));

        if ($normalized === [] || in_array('all', $normalized, true)) {
            return $definitions;
        }

        return array_intersect_key($definitions, array_flip($normalized));
    }

    /**
     * @param  array<string, array{label:string, basis:string, purge_allowed:bool, purge_mode:string, financial:bool, tables:array<int, array{table:string, date_column?:string, date_kind?:string, foreign_key?:string}>}>  $datasets
     * @return list<string>
     */
    public static function missing(array $datasets, array $requested): array
    {
        $resolved = array_keys($datasets);
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $key): string => strtolower(trim((string) $key)),
            $requested
        ))));

        return array_values(array_diff($normalized, $resolved));
    }
}
