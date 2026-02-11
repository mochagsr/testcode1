<?php

use App\Models\ReceivablePayment;
use App\Models\SalesReturn;
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
