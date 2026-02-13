<?php

use App\Models\ReceivablePayment;
use App\Models\ReceivableLedger;
use App\Models\OutgoingTransaction;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\AppSetting;
use App\Support\SemesterBookService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:normalize-doc-prefixes {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');

    $returnRows = SalesReturn::query()
        ->where('return_number', 'like', 'RTN-%')
        ->orWhere('return_number', 'like', 'RET-%')
        ->get(['id', 'return_number']);
    $paymentRows = ReceivablePayment::query()
        ->where('payment_number', 'like', 'PYT-%')
        ->get(['id', 'payment_number']);

    $returnChanges = [];
    foreach ($returnRows as $row) {
        $old = (string) $row->return_number;
        $new = preg_replace('/^(RTN|RET)-/i', 'RTR-', $old) ?: $old;
        if ($new !== $old) {
            $returnChanges[(int) $row->id] = ['old' => $old, 'new' => $new];
        }
    }

    $paymentChanges = [];
    foreach ($paymentRows as $row) {
        $old = (string) $row->payment_number;
        $new = preg_replace('/^PYT-/i', 'KWT-', $old) ?: $old;
        if ($new !== $old) {
            $paymentChanges[(int) $row->id] = ['old' => $old, 'new' => $new];
        }
    }

    $this->line('Planned changes:');
    $this->line('- Sales returns: '.count($returnChanges));
    $this->line('- Receivable payments: '.count($paymentChanges));

    $replaceMap = [];
    foreach ($returnChanges as $change) {
        $replaceMap[$change['old']] = $change['new'];
    }
    foreach ($paymentChanges as $change) {
        $replaceMap[$change['old']] = $change['new'];
    }

    if ($dryRun) {
        $this->warn('Dry run mode, no data changed.');
        return;
    }

    DB::transaction(function () use ($returnChanges, $paymentChanges, $replaceMap): void {
        foreach ($returnChanges as $id => $change) {
            SalesReturn::query()->whereKey($id)->update(['return_number' => $change['new']]);
        }
        foreach ($paymentChanges as $id => $change) {
            ReceivablePayment::query()->whereKey($id)->update(['payment_number' => $change['new']]);
        }

        if ($replaceMap !== []) {
            $targets = [
                ['table' => 'receivable_ledgers', 'column' => 'description'],
                ['table' => 'invoice_payments', 'column' => 'notes'],
                ['table' => 'audit_logs', 'column' => 'description'],
                ['table' => 'stock_mutations', 'column' => 'notes'],
                ['table' => 'receivable_payments', 'column' => 'notes'],
            ];

            foreach ($targets as $target) {
                DB::table($target['table'])
                    ->select(['id', $target['column']])
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) use ($target, $replaceMap): void {
                        foreach ($rows as $row) {
                            $oldText = (string) ($row->{$target['column']} ?? '');
                            if ($oldText === '') {
                                continue;
                            }
                            $newText = str_replace(array_keys($replaceMap), array_values($replaceMap), $oldText);
                            if ($newText === $oldText) {
                                continue;
                            }
                            DB::table($target['table'])
                                ->where('id', (int) $row->id)
                                ->update([$target['column'] => $newText]);
                        }
                    });
            }
        }
    });

    $this->info('Normalization completed.');
})->purpose('Normalize legacy document prefixes to RTR/KWT and update related text references');

Artisan::command('app:normalize-semester-codes {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    /** @var SemesterBookService $semesterBookService */
    $semesterBookService = app(SemesterBookService::class);

    $normalizeCode = function (?string $raw) use ($semesterBookService): ?string {
        $value = strtoupper(trim((string) $raw));
        if ($value === '') {
            return null;
        }

        return $semesterBookService->normalizeSemester($value);
    };

    $deriveFromDate = function (?string $date) use ($semesterBookService): ?string {
        if ($date === null || trim($date) === '') {
            return null;
        }
        try {
            return $semesterBookService->semesterFromDate(Carbon::parse($date)->format('Y-m-d'));
        } catch (\Throwable) {
            return null;
        }
    };

    $tablePlans = [
        ['table' => 'sales_invoices', 'key' => 'id', 'date' => 'invoice_date', 'period' => 'semester_period'],
        ['table' => 'sales_returns', 'key' => 'id', 'date' => 'return_date', 'period' => 'semester_period'],
        ['table' => 'outgoing_transactions', 'key' => 'id', 'date' => 'transaction_date', 'period' => 'semester_period'],
        ['table' => 'receivable_ledgers', 'key' => 'id', 'date' => 'entry_date', 'period' => 'period_code'],
    ];

    $tableChangeCounts = [];
    $tableUpdates = [];
    foreach ($tablePlans as $plan) {
        $changes = [];
        DB::table($plan['table'])
            ->select([$plan['key'], $plan['date'], $plan['period']])
            ->whereNotNull($plan['date'])
            ->orderBy($plan['key'])
            ->chunkById(500, function ($rows) use (&$changes, $plan, $deriveFromDate, $normalizeCode): void {
                foreach ($rows as $row) {
                    $derived = $deriveFromDate((string) $row->{$plan['date']});
                    if ($derived === null) {
                        continue;
                    }
                    $current = $normalizeCode((string) ($row->{$plan['period']} ?? ''));
                    if ($current === $derived) {
                        continue;
                    }
                    $changes[(int) $row->{$plan['key']}] = $derived;
                }
            }, $plan['key']);
        $tableUpdates[$plan['table']] = $changes;
        $tableChangeCounts[$plan['table']] = count($changes);
    }

    $settingsKeys = [
        'semester_period_options',
        'semester_active_periods',
        'closed_semester_periods',
    ];
    $settingsUpdates = [];
    foreach ($settingsKeys as $key) {
        $raw = (string) AppSetting::getValue($key, '');
        $normalized = collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn (string $item): ?string => $normalizeCode($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->unique()
            ->values()
            ->implode(',');
        if ($normalized !== trim($raw)) {
            $settingsUpdates[$key] = $normalized;
        }
    }

    $normalizeEntitySemesterList = function (string $raw) use ($normalizeCode): string {
        return collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(function (string $item) use ($normalizeCode): ?string {
                if (preg_match('/^(\d+)\s*[:|]\s*(.+)$/', $item, $matches) !== 1) {
                    return null;
                }
                $entityId = (int) $matches[1];
                if ($entityId <= 0) {
                    return null;
                }
                $semester = $normalizeCode((string) $matches[2]);
                if ($semester === null) {
                    return null;
                }
                return $entityId.':'.$semester;
            })
            ->filter(fn (?string $item): bool => $item !== null)
            ->unique()
            ->values()
            ->implode(',');
    };

    $customerSemesterRaw = (string) AppSetting::getValue('closed_customer_semester_periods', '');
    $customerSemesterNormalized = $normalizeEntitySemesterList($customerSemesterRaw);
    if ($customerSemesterNormalized !== trim($customerSemesterRaw)) {
        $settingsUpdates['closed_customer_semester_periods'] = $customerSemesterNormalized;
    }

    $supplierSemesterRaw = (string) AppSetting::getValue('closed_supplier_semester_periods', '');
    $supplierSemesterNormalized = $normalizeEntitySemesterList($supplierSemesterRaw);
    if ($supplierSemesterNormalized !== trim($supplierSemesterRaw)) {
        $settingsUpdates['closed_supplier_semester_periods'] = $supplierSemesterNormalized;
    }

    $this->line('Planned semester normalization:');
    foreach ($tablePlans as $plan) {
        $this->line('- '.$plan['table'].': '.($tableChangeCounts[$plan['table']] ?? 0).' row(s)');
    }
    $this->line('- settings keys: '.count($settingsUpdates));

    if ($dryRun) {
        $this->warn('Dry run mode, no data changed.');
        return;
    }

    DB::transaction(function () use ($tablePlans, $tableUpdates, $settingsUpdates): void {
        foreach ($tablePlans as $plan) {
            $updates = $tableUpdates[$plan['table']] ?? [];
            foreach ($updates as $id => $semesterCode) {
                DB::table($plan['table'])
                    ->where($plan['key'], (int) $id)
                    ->update([$plan['period'] => $semesterCode]);
            }
        }

        foreach ($settingsUpdates as $key => $value) {
            AppSetting::setValue((string) $key, (string) $value);
        }
    });

    $this->info('Semester normalization completed.');
})->purpose('Normalize semester codes to academic format S1-2526/S2-2526 based on transaction dates');
