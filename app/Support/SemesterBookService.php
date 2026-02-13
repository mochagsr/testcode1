<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\SalesInvoice;
use Carbon\Carbon;

class SemesterBookService
{
    /**
     * @return array<int, string>
     */
    public function closedSupplierSemesters(): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('closed_supplier_semester_periods', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->map(fn (string $item): ?string => $this->normalizeSupplierSemesterKey($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->values()
            ->all();
    }

    public function isSupplierClosed(?int $supplierId, ?string $semester): bool
    {
        $normalizedSemester = $this->normalizeSemester((string) $semester);
        $normalizedSupplierId = (int) ($supplierId ?? 0);
        if ($normalizedSemester === null || $normalizedSupplierId <= 0) {
            return false;
        }

        $key = $this->supplierSemesterKey($normalizedSupplierId, $normalizedSemester);

        return in_array($key, $this->closedSupplierSemesters(), true);
    }

    /**
     * @param  iterable<int, int|string>  $supplierIds
     * @return array<int, bool>
     */
    public function supplierSemesterClosedStates(iterable $supplierIds, string $semester): array
    {
        $normalizedSemester = $this->normalizeSemester($semester);
        if ($normalizedSemester === null) {
            return [];
        }

        $ids = collect($supplierIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $closedMap = collect($this->closedSupplierSemesters())
            ->filter(fn (string $key): bool => str_ends_with($key, ':'.$normalizedSemester))
            ->mapWithKeys(function (string $key): array {
                [$supplierId] = explode(':', $key, 2);

                return [(int) $supplierId => true];
            });

        $states = [];
        foreach ($ids as $supplierId) {
            $states[$supplierId] = (bool) $closedMap->get($supplierId, false);
        }

        return $states;
    }

    public function closeSupplierSemester(int $supplierId, string $semester): void
    {
        $normalizedSemester = $this->normalizeSemester($semester);
        $normalizedSupplierId = (int) $supplierId;
        if ($normalizedSemester === null || $normalizedSupplierId <= 0) {
            return;
        }

        $items = collect($this->closedSupplierSemesters())
            ->push($this->supplierSemesterKey($normalizedSupplierId, $normalizedSemester))
            ->unique()
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_supplier_semester_periods', $items);
    }

    public function openSupplierSemester(int $supplierId, string $semester): void
    {
        $normalizedSemester = $this->normalizeSemester($semester);
        $normalizedSupplierId = (int) $supplierId;
        if ($normalizedSemester === null || $normalizedSupplierId <= 0) {
            return;
        }

        $target = $this->supplierSemesterKey($normalizedSupplierId, $normalizedSemester);
        $items = collect($this->closedSupplierSemesters())
            ->reject(fn (string $item): bool => $item === $target)
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_supplier_semester_periods', $items);
    }

    /**
     * @return array<int, string>
     */
    public function closedCustomerSemesters(): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('closed_customer_semester_periods', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->map(fn (string $item): ?string => $this->normalizeCustomerSemesterKey($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->values()
            ->all();
    }

    public function isCustomerClosed(?int $customerId, ?string $semester): bool
    {
        $normalizedSemester = $this->normalizeSemester((string) $semester);
        $normalizedCustomerId = (int) ($customerId ?? 0);
        if ($normalizedSemester === null || $normalizedCustomerId <= 0) {
            return false;
        }

        $key = $this->customerSemesterKey($normalizedCustomerId, $normalizedSemester);

        return in_array($key, $this->closedCustomerSemesters(), true);
    }

    public function isCustomerAutoClosed(?int $customerId, ?string $semester): bool
    {
        $normalizedSemester = $this->normalizeSemester((string) $semester);
        $normalizedCustomerId = (int) ($customerId ?? 0);
        if ($normalizedSemester === null || $normalizedCustomerId <= 0) {
            return false;
        }

        $aggregate = SalesInvoice::query()
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(balance), 0) as outstanding')
            ->where('customer_id', $normalizedCustomerId)
            ->where('is_canceled', false)
            ->where('semester_period', $normalizedSemester)
            ->first();

        $invoiceCount = (int) ($aggregate?->invoice_count ?? 0);
        $outstanding = (float) ($aggregate?->outstanding ?? 0);

        return $invoiceCount > 0 && round($outstanding) <= 0;
    }

    public function isCustomerLocked(?int $customerId, ?string $semester): bool
    {
        return $this->isCustomerClosed($customerId, $semester)
            || $this->isCustomerAutoClosed($customerId, $semester);
    }

    /**
     * @param  iterable<int, int|string>  $customerIds
     * @return array<int, array{locked:bool,manual:bool,auto:bool,outstanding:int,invoice_count:int}>
     */
    public function customerSemesterLockStates(iterable $customerIds, string $semester): array
    {
        $normalizedSemester = $this->normalizeSemester($semester);
        if ($normalizedSemester === null) {
            return [];
        }

        $ids = collect($customerIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $aggregates = SalesInvoice::query()
            ->select('customer_id')
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(balance), 0) as outstanding')
            ->whereIn('customer_id', $ids->all())
            ->where('is_canceled', false)
            ->where('semester_period', $normalizedSemester)
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $manualClosedMap = collect($this->closedCustomerSemesters())
            ->filter(fn (string $key): bool => str_ends_with($key, ':'.$normalizedSemester))
            ->mapWithKeys(function (string $key): array {
                [$customerId] = explode(':', $key, 2);

                return [(int) $customerId => true];
            });

        $states = [];
        foreach ($ids as $customerId) {
            $aggregate = $aggregates->get($customerId);
            $invoiceCount = (int) ($aggregate?->invoice_count ?? 0);
            $outstanding = (int) round((float) ($aggregate?->outstanding ?? 0));
            $manual = (bool) $manualClosedMap->get($customerId, false);
            $auto = $invoiceCount > 0 && $outstanding <= 0;

            $states[$customerId] = [
                'locked' => $manual || $auto,
                'manual' => $manual,
                'auto' => $auto,
                'outstanding' => $outstanding,
                'invoice_count' => $invoiceCount,
            ];
        }

        return $states;
    }

    public function closeCustomerSemester(int $customerId, string $semester): void
    {
        $normalizedSemester = $this->normalizeSemester($semester);
        $normalizedCustomerId = (int) $customerId;
        if ($normalizedSemester === null || $normalizedCustomerId <= 0) {
            return;
        }

        $items = collect($this->closedCustomerSemesters())
            ->push($this->customerSemesterKey($normalizedCustomerId, $normalizedSemester))
            ->unique()
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_customer_semester_periods', $items);
    }

    public function openCustomerSemester(int $customerId, string $semester): void
    {
        $normalizedSemester = $this->normalizeSemester($semester);
        $normalizedCustomerId = (int) $customerId;
        if ($normalizedSemester === null || $normalizedCustomerId <= 0) {
            return;
        }

        $target = $this->customerSemesterKey($normalizedCustomerId, $normalizedSemester);
        $items = collect($this->closedCustomerSemesters())
            ->reject(fn (string $item): bool => $item === $target)
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_customer_semester_periods', $items);
    }

    /**
     * @return array<int, string>
     */
    public function activeSemesters(): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('semester_active_periods', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->map(fn (string $item): ?string => $this->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->values()
            ->all();
    }

    public function isActive(?string $semester): bool
    {
        $normalized = $this->normalizeSemester((string) $semester);
        if ($normalized === null) {
            return false;
        }

        $activeSemesters = $this->activeSemesters();
        if ($activeSemesters === []) {
            return true;
        }

        return in_array($normalized, $activeSemesters, true);
    }

    /**
     * @param  iterable<int, string>  $options
     * @return array<int, string>
     */
    public function filterToActiveSemesters(iterable $options): array
    {
        $normalizedOptions = collect($options)
            ->map(fn (string $item): ?string => $this->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->values();

        $activeSemesters = collect($this->activeSemesters());
        if ($activeSemesters->isEmpty()) {
            return $normalizedOptions->all();
        }

        return $normalizedOptions
            ->filter(fn (string $item): bool => $activeSemesters->contains($item))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function closedSemesters(): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('closed_semester_periods', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->map(fn (string $item): ?string => $this->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->values()
            ->all();
    }

    public function isClosed(?string $semester): bool
    {
        $normalized = $this->normalizeSemester((string) $semester);
        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, $this->closedSemesters(), true);
    }

    public function closeSemester(string $semester): void
    {
        $normalized = $this->normalizeSemester($semester);
        if ($normalized === null) {
            return;
        }

        $items = collect($this->closedSemesters())
            ->push($normalized)
            ->unique()
            ->sortDesc()
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_semester_periods', $items);
    }

    public function openSemester(string $semester): void
    {
        $normalized = $this->normalizeSemester($semester);
        if ($normalized === null) {
            return;
        }

        $items = collect($this->closedSemesters())
            ->reject(fn (string $item): bool => $item === $normalized)
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_semester_periods', $items);
    }

    public function normalizeSemester(string $semester): ?string
    {
        $value = strtoupper(trim($semester));
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $value, $matches) === 1) {
            $semesterNo = (int) $matches[1];
            $startYear2D = (int) $matches[2];
            $endYear2D = (int) $matches[3];
            $expectedEndYear = ($startYear2D + 1) % 100;
            if ($endYear2D !== $expectedEndYear) {
                return null;
            }

            return sprintf('S%d-%02d%02d', $semesterNo, $startYear2D, $endYear2D);
        }

        return null;
    }

    public function semesterFromDate(?string $date): ?string
    {
        $value = trim((string) $date);
        if ($value === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        $month = (int) $parsed->format('n');
        $year = (int) $parsed->format('Y');

        // Academic semester:
        // S1: May-Oct, S2: Nov-Apr.
        if ($month >= 5 && $month <= 10) {
            $startYear = $year;
            $endYear = $year + 1;
            return sprintf('S1-%02d%02d', $startYear % 100, $endYear % 100);
        }

        if ($month >= 11) {
            $startYear = $year;
            $endYear = $year + 1;
            return sprintf('S2-%02d%02d', $startYear % 100, $endYear % 100);
        }

        // Jan-Apr belongs to previous academic year's S2.
        $startYear = $year - 1;
        $endYear = $year;
        return sprintf('S2-%02d%02d', $startYear % 100, $endYear % 100);
    }

    public function currentSemester(): string
    {
        return $this->semesterFromDate(now()->format('Y-m-d')) ?? 'S1-'.now()->format('y').now()->addYear()->format('y');
    }

    public function previousSemester(string $period): string
    {
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $period, $matches) === 1) {
            $semester = (int) $matches[1];
            $startYear = 2000 + (int) $matches[2];
            $endYear = 2000 + (int) $matches[3];

            if ($semester === 2) {
                return sprintf('S1-%02d%02d', $startYear % 100, $endYear % 100);
            }

            $prevStartYear = $startYear - 1;
            $prevEndYear = $endYear - 1;
            return sprintf('S2-%02d%02d', $prevStartYear % 100, $prevEndYear % 100);
        }

        return $this->semesterFromDate(now()->subMonths(6)->format('Y-m-d')) ?? $this->currentSemester();
    }

    private function normalizeCustomerSemesterKey(string $value): ?string
    {
        if (preg_match('/^(\d+)\s*[:|]\s*(S[12]-\d{4})$/i', trim($value), $matches) !== 1) {
            return null;
        }

        $customerId = (int) $matches[1];
        $semester = $this->normalizeSemester((string) $matches[2]);
        if ($customerId <= 0 || $semester === null) {
            return null;
        }

        return $this->customerSemesterKey($customerId, $semester);
    }

    private function customerSemesterKey(int $customerId, string $semester): string
    {
        return $customerId.':'.$semester;
    }

    private function normalizeSupplierSemesterKey(string $value): ?string
    {
        if (preg_match('/^(\d+)\s*[:|]\s*(S[12]-\d{4})$/i', trim($value), $matches) !== 1) {
            return null;
        }

        $supplierId = (int) $matches[1];
        $semester = $this->normalizeSemester((string) $matches[2]);
        if ($supplierId <= 0 || $semester === null) {
            return null;
        }

        return $this->supplierSemesterKey($supplierId, $semester);
    }

    private function supplierSemesterKey(int $supplierId, string $semester): string
    {
        return $supplierId.':'.$semester;
    }
}
