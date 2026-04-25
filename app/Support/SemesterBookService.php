<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\SalesInvoice;
use Carbon\Carbon;

class SemesterBookService
{
    /**
     * @var array<string, string|null>
     */
    private array $normalizeSemesterCache = [];

    /**
     * @var array<string, string|null>
     */
    private array $semesterFromDateCache = [];

    /**
     * @var array<int, string>|null
     */
    private ?array $configuredSemesterOptionsCache = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $closedSupplierSemestersCache = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $closedSupplierMonthsCache = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $closedCustomerSemestersCache = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $activeSemestersCache = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $closedSemestersCache = null;

    /**
     * @var array<string, array{created_at?:string|null}>|null
     */
    private ?array $configuredSemesterMetadataCache = null;

    /**
     * @var array<string, array{closed_at?:string|null}>|null
     */
    private ?array $closedSemesterMetadataCache = null;

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function configuredSemesterOptions(): \Illuminate\Support\Collection
    {
        if (is_array($this->configuredSemesterOptionsCache)) {
            return collect($this->configuredSemesterOptionsCache);
        }

        $this->configuredSemesterOptionsCache = collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('semester_period_options', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all();

        $this->configuredSemesterOptionsCache = $this->sortSemesterCollection(collect($this->configuredSemesterOptionsCache))
            ->values()
            ->all();

        return collect($this->configuredSemesterOptionsCache);
    }

    /**
     * @return array<string, array{created_at?:string|null}>
     */
    public function configuredSemesterMetadata(): array
    {
        if (is_array($this->configuredSemesterMetadataCache)) {
            return $this->configuredSemesterMetadataCache;
        }

        $decoded = json_decode((string) AppSetting::getValue('semester_period_metadata', '{}'), true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $metadata = [];
        foreach ($decoded as $semester => $item) {
            $normalized = $this->normalizeSemester((string) $semester);
            if ($normalized === null || ! is_array($item)) {
                continue;
            }

            $metadata[$normalized] = [
                'created_at' => isset($item['created_at']) ? (string) $item['created_at'] : null,
            ];
        }

        $this->configuredSemesterMetadataCache = $metadata;

        return $this->configuredSemesterMetadataCache;
    }

    /**
     * Build normalized semester options from mixed sources.
     *
     * @param  iterable<int, string>  $options
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function buildSemesterOptionCollection(
        iterable $options,
        bool $activeOnly = false,
        bool $includeCurrentAndPrevious = true
    ): \Illuminate\Support\Collection {
        $normalized = collect($options)
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(fn (string $item): ?string => $this->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null);

        if ($includeCurrentAndPrevious) {
            $current = $this->currentSemester();
            $normalized = $normalized
                ->push($current)
                ->push($this->previousSemester($current));
        }

        $normalized = $this->sortSemesterCollection(
            $normalized
                ->unique()
                ->values()
        );

        if ($activeOnly) {
            return collect($this->filterToActiveSemesters($normalized->all()))->values();
        }

        return $normalized->values();
    }

    /**
     * @return array<int, string>
     */
    public function closedSupplierYears(): array
    {
        if (is_array($this->closedSupplierSemestersCache)) {
            return $this->closedSupplierSemestersCache;
        }

        $rawValues = collect([
            (string) AppSetting::getValue('closed_supplier_year_periods', ''),
            (string) AppSetting::getValue('closed_supplier_semester_periods', ''),
        ])->implode(',');

        $this->closedSupplierSemestersCache = collect(preg_split('/[\r\n,]+/', $rawValues) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->map(fn (string $item): ?string => $this->normalizeSupplierYearKey($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->unique()
            ->values()
            ->all();

        return $this->closedSupplierSemestersCache;
    }

    public function closedSupplierSemesters(): array
    {
        return $this->closedSupplierYears();
    }

    /**
     * @return array<int, string>
     */
    public function closedSupplierMonths(): array
    {
        if (is_array($this->closedSupplierMonthsCache)) {
            return $this->closedSupplierMonthsCache;
        }

        $this->closedSupplierMonthsCache = collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('closed_supplier_month_periods', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->map(fn (string $item): ?string => $this->normalizeSupplierMonthKey($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->unique()
            ->values()
            ->all();

        return $this->closedSupplierMonthsCache;
    }

    public function isSupplierYearClosed(?int $supplierId, ?string $year): bool
    {
        $normalizedYear = $this->normalizeYear((string) $year);
        $normalizedSupplierId = (int) ($supplierId ?? 0);
        if ($normalizedYear === null || $normalizedSupplierId <= 0) {
            return false;
        }

        $key = $this->supplierYearKey($normalizedSupplierId, $normalizedYear);

        return in_array($key, $this->closedSupplierYears(), true);
    }

    public function isSupplierMonthClosed(?int $supplierId, ?string $year, ?int $month): bool
    {
        $normalizedYear = $this->normalizeYear((string) $year);
        $normalizedMonth = (int) ($month ?? 0);
        $normalizedSupplierId = (int) ($supplierId ?? 0);
        if ($normalizedYear === null || $normalizedMonth < 1 || $normalizedMonth > 12 || $normalizedSupplierId <= 0) {
            return false;
        }

        $monthKey = $this->supplierMonthKey($normalizedSupplierId, $normalizedYear, $normalizedMonth);
        if (in_array($monthKey, $this->closedSupplierMonths(), true)) {
            return true;
        }

        return $this->isSupplierYearClosed($normalizedSupplierId, $normalizedYear);
    }

    /**
     * @param  iterable<int, int|string>  $supplierIds
     * @return array<int, bool>
     */
    public function supplierYearClosedStates(iterable $supplierIds, string $year): array
    {
        $normalizedYear = $this->normalizeYear($year);
        if ($normalizedYear === null) {
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

        $closedMap = collect($this->closedSupplierYears())
            ->filter(fn (string $key): bool => str_ends_with($key, ':'.$normalizedYear))
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

    public function supplierSemesterClosedStates(iterable $supplierIds, string $year): array
    {
        return $this->supplierYearClosedStates($supplierIds, $year);
    }

    /**
     * @param  iterable<int, int|string>  $supplierIds
     * @return array<int, bool>
     */
    public function supplierMonthClosedStates(iterable $supplierIds, string $year, int $month): array
    {
        $normalizedYear = $this->normalizeYear($year);
        if ($normalizedYear === null || $month < 1 || $month > 12) {
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

        $closedMonthMap = collect($this->closedSupplierMonths())
            ->filter(fn (string $key): bool => str_ends_with($key, ':'.$normalizedYear.'-'.sprintf('%02d', $month)))
            ->mapWithKeys(function (string $key): array {
                [$supplierId] = explode(':', $key, 2);

                return [(int) $supplierId => true];
            });

        $closedYearMap = collect($this->closedSupplierYears())
            ->filter(fn (string $key): bool => str_ends_with($key, ':'.$normalizedYear))
            ->mapWithKeys(function (string $key): array {
                [$supplierId] = explode(':', $key, 2);

                return [(int) $supplierId => true];
            });

        $states = [];
        foreach ($ids as $supplierId) {
            $states[$supplierId] = (bool) $closedMonthMap->get($supplierId, false) || (bool) $closedYearMap->get($supplierId, false);
        }

        return $states;
    }

    public function closeSupplierYear(int $supplierId, string $year): void
    {
        $normalizedYear = $this->normalizeYear($year);
        $normalizedSupplierId = (int) $supplierId;
        if ($normalizedYear === null || $normalizedSupplierId <= 0) {
            return;
        }

        $items = collect($this->closedSupplierYears())
            ->push($this->supplierYearKey($normalizedSupplierId, $normalizedYear))
            ->unique()
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_supplier_year_periods', $items);
        $this->invalidateSemesterCaches();
    }

    public function closeSupplierSemester(int $supplierId, string $year): void
    {
        $this->closeSupplierYear($supplierId, $year);
    }

    public function closeSupplierMonth(int $supplierId, string $year, int $month): void
    {
        $normalizedYear = $this->normalizeYear($year);
        $normalizedSupplierId = (int) $supplierId;
        if ($normalizedYear === null || $month < 1 || $month > 12 || $normalizedSupplierId <= 0) {
            return;
        }

        $items = collect($this->closedSupplierMonths())
            ->push($this->supplierMonthKey($normalizedSupplierId, $normalizedYear, $month))
            ->unique()
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_supplier_month_periods', $items);
        $this->invalidateSemesterCaches();
    }

    public function openSupplierYear(int $supplierId, string $year): void
    {
        $normalizedYear = $this->normalizeYear($year);
        $normalizedSupplierId = (int) $supplierId;
        if ($normalizedYear === null || $normalizedSupplierId <= 0) {
            return;
        }

        $target = $this->supplierYearKey($normalizedSupplierId, $normalizedYear);
        $items = collect($this->closedSupplierYears())
            ->reject(fn (string $item): bool => $item === $target)
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_supplier_year_periods', $items);
        $this->invalidateSemesterCaches();
    }

    public function openSupplierSemester(int $supplierId, string $year): void
    {
        $this->openSupplierYear($supplierId, $year);
    }

    public function openSupplierMonth(int $supplierId, string $year, int $month): void
    {
        $normalizedYear = $this->normalizeYear($year);
        $normalizedSupplierId = (int) $supplierId;
        if ($normalizedYear === null || $month < 1 || $month > 12 || $normalizedSupplierId <= 0) {
            return;
        }

        $target = $this->supplierMonthKey($normalizedSupplierId, $normalizedYear, $month);
        $items = collect($this->closedSupplierMonths())
            ->reject(fn (string $item): bool => $item === $target)
            ->values()
            ->implode(',');

        AppSetting::setValue('closed_supplier_month_periods', $items);
        $this->invalidateSemesterCaches();
    }

    public function isSupplierClosed(?int $supplierId, ?string $year): bool
    {
        return $this->isSupplierYearClosed($supplierId, $year);
    }

    /**
     * @return array<int, string>
     */
    public function closedCustomerSemesters(): array
    {
        if (is_array($this->closedCustomerSemestersCache)) {
            return $this->closedCustomerSemestersCache;
        }

        $this->closedCustomerSemestersCache = collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('closed_customer_semester_periods', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->map(fn (string $item): ?string => $this->normalizeCustomerSemesterKey($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->values()
            ->all();

        return $this->closedCustomerSemestersCache;
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
        // Paid-off customers are only "ready to close"; the actual lock is manual.
        return false;
    }

    public function isCustomerLocked(?int $customerId, ?string $semester): bool
    {
        return $this->isCustomerClosed($customerId, $semester);
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
            $auto = false;

            $states[$customerId] = [
                'locked' => $manual,
                'manual' => $manual,
                'auto' => $auto,
                'outstanding' => $outstanding,
                'invoice_count' => $invoiceCount,
            ];
        }

        return $states;
    }

    /**
     * @param  iterable<int, array{customer_id:int|string, semester:string}>  $pairs
     * @return array<string, array{locked:bool,manual:bool,auto:bool,outstanding:int,invoice_count:int}>
     */
    public function customerSemesterLockStatesByPairs(iterable $pairs): array
    {
        $normalizedPairs = collect($pairs)
            ->map(function (array $pair): ?array {
                $customerId = (int) ($pair['customer_id'] ?? 0);
                $semester = $this->normalizeSemester((string) ($pair['semester'] ?? ''));
                if ($customerId <= 0 || $semester === null) {
                    return null;
                }

                return [
                    'customer_id' => $customerId,
                    'semester' => $semester,
                    'key' => $customerId.':'.$semester,
                ];
            })
            ->filter()
            ->unique('key')
            ->values();

        if ($normalizedPairs->isEmpty()) {
            return [];
        }

        $customerIds = $normalizedPairs->pluck('customer_id')->unique()->values();
        $semesterCodes = $normalizedPairs->pluck('semester')->unique()->values();

        $aggregates = SalesInvoice::query()
            ->select('customer_id', 'semester_period')
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(balance), 0) as outstanding')
            ->whereIn('customer_id', $customerIds->all())
            ->whereIn('semester_period', $semesterCodes->all())
            ->where('is_canceled', false)
            ->groupBy('customer_id', 'semester_period')
            ->get();

        $aggregateMap = [];
        foreach ($aggregates as $aggregate) {
            $key = ((int) $aggregate->customer_id).':'.(string) $aggregate->semester_period;
            $aggregateMap[$key] = [
                'invoice_count' => (int) ($aggregate->invoice_count ?? 0),
                'outstanding' => (int) round((float) ($aggregate->outstanding ?? 0)),
            ];
        }

        $manualMap = collect($this->closedCustomerSemesters())
            ->filter(function (string $key) use ($customerIds, $semesterCodes): bool {
                [$customerIdRaw, $semester] = array_pad(explode(':', $key, 2), 2, '');
                $customerId = (int) $customerIdRaw;

                return $customerIds->contains($customerId) && $semesterCodes->contains($semester);
            })
            ->mapWithKeys(fn (string $key): array => [$key => true])
            ->all();

        $states = [];
        foreach ($normalizedPairs as $pair) {
            $key = (string) $pair['key'];
            $invoiceCount = (int) ($aggregateMap[$key]['invoice_count'] ?? 0);
            $outstanding = (int) ($aggregateMap[$key]['outstanding'] ?? 0);
            $manual = (bool) ($manualMap[$key] ?? false);
            $auto = false;

            $states[$key] = [
                'locked' => $manual,
                'manual' => $manual,
                'auto' => $auto,
                'outstanding' => $outstanding,
                'invoice_count' => $invoiceCount,
            ];
        }

        return $states;
    }

    /**
     * @return array{
     *     semester:string|null,
     *     customer_count:int,
     *     paid_customer_count:int,
     *     open_customer_count:int,
     *     total_outstanding:int,
     *     ready_to_close:bool,
     *     already_closed:bool
     * }
     */
    public function receivableSemesterClosingState(?string $semester): array
    {
        $normalizedSemester = $this->normalizeSemester((string) $semester);
        if ($normalizedSemester === null) {
            return [
                'semester' => null,
                'customer_count' => 0,
                'paid_customer_count' => 0,
                'open_customer_count' => 0,
                'total_outstanding' => 0,
                'ready_to_close' => false,
                'already_closed' => false,
            ];
        }

        $aggregates = SalesInvoice::query()
            ->select('customer_id')
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(balance), 0) as outstanding')
            ->where('is_canceled', false)
            ->where('semester_period', $normalizedSemester)
            ->groupBy('customer_id')
            ->get();

        $customerCount = $aggregates->count();
        $paidCustomerCount = $aggregates
            ->filter(fn ($row): bool => (int) round((float) ($row->outstanding ?? 0)) <= 0)
            ->count();
        $openCustomerCount = max(0, $customerCount - $paidCustomerCount);
        $totalOutstanding = (int) round(
            (float) $aggregates->sum(fn ($row): float => max(0, (float) ($row->outstanding ?? 0)))
        );
        $alreadyClosed = $this->isClosed($normalizedSemester);
        $readyToClose = $customerCount > 0 && $openCustomerCount === 0 && ! $alreadyClosed;

        return [
            'semester' => $normalizedSemester,
            'customer_count' => $customerCount,
            'paid_customer_count' => $paidCustomerCount,
            'open_customer_count' => $openCustomerCount,
            'total_outstanding' => $totalOutstanding,
            'ready_to_close' => $readyToClose,
            'already_closed' => $alreadyClosed,
        ];
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
        $this->invalidateSemesterCaches();
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
        $this->invalidateSemesterCaches();
    }

    /**
     * @return array<int, string>
     */
    public function activeSemesters(): array
    {
        if (is_array($this->activeSemestersCache)) {
            return $this->activeSemestersCache;
        }

        $this->activeSemestersCache = collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('semester_active_periods', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->map(fn (string $item): ?string => $this->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->values()
            ->all();

        return $this->activeSemestersCache;
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
     * @param  iterable<int, string>  $options
     * @return array<int, string>
     */
    public function filterToOpenSemesters(iterable $options, bool $activeOnly = false): array
    {
        $normalizedOptions = collect($options)
            ->map(fn (string $item): ?string => $this->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->values();

        if ($activeOnly) {
            $activeSemesters = collect($this->activeSemesters());
            if ($activeSemesters->isNotEmpty()) {
                $normalizedOptions = $normalizedOptions
                    ->filter(fn (string $item): bool => $activeSemesters->contains($item))
                    ->values();
            }
        }

        $closedSemesters = collect($this->closedSemesters());
        if ($closedSemesters->isNotEmpty()) {
            $normalizedOptions = $normalizedOptions
                ->reject(fn (string $item): bool => $closedSemesters->contains($item))
                ->values();
        }

        return $normalizedOptions->all();
    }

    /**
     * @return array<int, string>
     */
    public function closedSemesters(): array
    {
        if (is_array($this->closedSemestersCache)) {
            return $this->closedSemestersCache;
        }

        $this->closedSemestersCache = $this->sortSemesterCollection(
            collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('closed_semester_periods', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->map(fn (string $item): ?string => $this->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->values()
        )->all();

        return $this->closedSemestersCache;
    }

    /**
     * @return array<int, string>
     */
    public function closedArchiveYearOptions(): array
    {
        return collect($this->closedSemesters())
            ->map(function (string $semester): ?string {
                if (preg_match('/^S[12]-(\d{4})$/', $semester, $matches) !== 1) {
                    return null;
                }

                return (string) $matches[1];
            })
            ->filter(fn (?string $yearCode): bool => $yearCode !== null && $yearCode !== '')
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{closed_at?:string|null}>
     */
    public function closedSemesterMetadata(): array
    {
        if (is_array($this->closedSemesterMetadataCache)) {
            return $this->closedSemesterMetadataCache;
        }

        $decoded = json_decode((string) AppSetting::getValue('closed_semester_period_metadata', '{}'), true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $metadata = [];
        foreach ($decoded as $semester => $item) {
            $normalized = $this->normalizeSemester((string) $semester);
            if ($normalized === null || ! is_array($item)) {
                continue;
            }

            $metadata[$normalized] = [
                'closed_at' => isset($item['closed_at']) ? (string) $item['closed_at'] : null,
            ];
        }

        $this->closedSemesterMetadataCache = $metadata;

        return $this->closedSemesterMetadataCache;
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

        $items = $this->sortSemesterCollection(
            collect($this->closedSemesters())
                ->push($normalized)
                ->unique()
                ->values()
        )->implode(',');

        $metadata = $this->closedSemesterMetadata();
        $metadata[$normalized] = [
            'closed_at' => now()->format('Y-m-d H:i:s'),
        ];

        AppSetting::setValues([
            'closed_semester_periods' => $items,
            'closed_semester_period_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
        $this->invalidateSemesterCaches();
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

        $metadata = $this->closedSemesterMetadata();
        unset($metadata[$normalized]);

        AppSetting::setValues([
            'closed_semester_periods' => $items,
            'closed_semester_period_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
        $this->invalidateSemesterCaches();
    }

    public function normalizeSemester(string $semester): ?string
    {
        $value = strtoupper(trim($semester));
        if ($value === '') {
            return null;
        }

        if (array_key_exists($value, $this->normalizeSemesterCache)) {
            return $this->normalizeSemesterCache[$value];
        }

        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $value, $matches) === 1) {
            $semesterNo = (int) $matches[1];
            $startYear2D = (int) $matches[2];
            $endYear2D = (int) $matches[3];
            $expectedEndYear = ($startYear2D + 1) % 100;
            if ($endYear2D !== $expectedEndYear) {
                $this->normalizeSemesterCache[$value] = null;
                return $this->normalizeSemesterCache[$value];
            }

            $this->normalizeSemesterCache[$value] = sprintf('S%d-%02d%02d', $semesterNo, $startYear2D, $endYear2D);
            return $this->normalizeSemesterCache[$value];
        }

        $this->normalizeSemesterCache[$value] = null;
        return $this->normalizeSemesterCache[$value];
    }

    public function semesterFromDate(?string $date): ?string
    {
        $value = trim((string) $date);
        if ($value === '') {
            return null;
        }

        if (array_key_exists($value, $this->semesterFromDateCache)) {
            return $this->semesterFromDateCache[$value];
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (\Throwable) {
            $this->semesterFromDateCache[$value] = null;
            return $this->semesterFromDateCache[$value];
        }

        $month = (int) $parsed->format('n');
        $year = (int) $parsed->format('Y');

        // Academic semester:
        // S1: May-Oct, S2: Nov-Apr.
        if ($month >= 5 && $month <= 10) {
            $startYear = $year;
            $endYear = $year + 1;
            $this->semesterFromDateCache[$value] = sprintf('S1-%02d%02d', $startYear % 100, $endYear % 100);
            return $this->semesterFromDateCache[$value];
        }

        if ($month >= 11) {
            $startYear = $year;
            $endYear = $year + 1;
            $this->semesterFromDateCache[$value] = sprintf('S2-%02d%02d', $startYear % 100, $endYear % 100);
            return $this->semesterFromDateCache[$value];
        }

        // Jan-Apr belongs to previous academic year's S2.
        $startYear = $year - 1;
        $endYear = $year;
        $this->semesterFromDateCache[$value] = sprintf('S2-%02d%02d', $startYear % 100, $endYear % 100);
        return $this->semesterFromDateCache[$value];
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

    /**
     * @return array{start:string,end:string}|null
     */
    public function semesterDateRange(?string $period): ?array
    {
        if ($period === null) {
            return null;
        }

        $normalized = $this->normalizeSemester($period);
        if ($normalized === null) {
            return null;
        }

        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $normalized, $matches) !== 1) {
            return null;
        }

        $half = (int) $matches[1];
        $startYear = 2000 + (int) $matches[2];
        $endYear = 2000 + (int) $matches[3];

        if ($half === 1) {
            return [
                'start' => Carbon::create($startYear, 5, 1)->startOfDay()->toDateString(),
                'end' => Carbon::create($startYear, 10, 31)->endOfDay()->toDateString(),
            ];
        }

        return [
            'start' => Carbon::create($startYear, 11, 1)->startOfDay()->toDateString(),
            'end' => Carbon::create($endYear, 4, 30)->endOfDay()->toDateString(),
        ];
    }

    /**
     * @return array{start:string,end:string,label:string,start_year:int,end_year:int}|null
     */
    public function archiveYearDateRange(?string $yearCode): ?array
    {
        $value = trim((string) $yearCode);
        if (preg_match('/^(\d{2})(\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        $startYear2D = (int) $matches[1];
        $endYear2D = (int) $matches[2];
        if ($endYear2D !== (($startYear2D + 1) % 100)) {
            return null;
        }

        $startYear = 2000 + $startYear2D;
        $endYear = 2000 + $endYear2D;

        return [
            'start' => Carbon::create($startYear, 5, 1)->startOfDay()->toDateString(),
            'end' => Carbon::create($endYear, 4, 30)->endOfDay()->toDateString(),
            'label' => $value,
            'start_year' => $startYear,
            'end_year' => $endYear,
        ];
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

    public function yearFromDate(?string $date): ?string
    {
        $value = trim((string) $date);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y');
        } catch (\Throwable) {
            return null;
        }
    }

    public function normalizeYear(?string $year): ?string
    {
        $value = trim((string) $year);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function normalizeSupplierYearKey(string $value): ?string
    {
        $trimmed = trim($value);
        if (preg_match('/^(\d+)\s*[:|]\s*(\d{4})$/', $trimmed, $matches) === 1) {
            $supplierId = (int) $matches[1];
            $year = $this->normalizeYear((string) $matches[2]);
            if ($supplierId <= 0 || $year === null) {
                return null;
            }

            return $this->supplierYearKey($supplierId, $year);
        }

        if (preg_match('/^(\d+)\s*[:|]\s*(S[12]-\d{4})$/i', $trimmed, $matches) !== 1) {
            return null;
        }

        $supplierId = (int) $matches[1];
        $semester = $this->normalizeSemester((string) $matches[2]);
        $year = $semester !== null ? $this->yearFromSemester($semester) : null;
        if ($supplierId <= 0 || $year === null) {
            return null;
        }

        return $this->supplierYearKey($supplierId, $year);
    }

    private function normalizeSupplierMonthKey(string $value): ?string
    {
        $trimmed = trim($value);
        if (preg_match('/^(\d+)\s*[:|]\s*(\d{4})-(\d{2})$/', $trimmed, $matches) !== 1) {
            return null;
        }

        $supplierId = (int) $matches[1];
        $year = $this->normalizeYear((string) $matches[2]);
        $month = (int) $matches[3];
        if ($supplierId <= 0 || $year === null || $month < 1 || $month > 12) {
            return null;
        }

        return $this->supplierMonthKey($supplierId, $year, $month);
    }

    private function supplierYearKey(int $supplierId, string $year): string
    {
        return $supplierId.':'.$year;
    }

    private function supplierMonthKey(int $supplierId, string $year, int $month): string
    {
        return $supplierId.':'.$year.'-'.sprintf('%02d', $month);
    }

    private function yearFromSemester(string $semester): ?string
    {
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $semester, $matches) !== 1) {
            return null;
        }

        $part = (int) $matches[1];
        $startYear = 2000 + (int) $matches[2];
        $endYear = 2000 + (int) $matches[3];

        return (string) ($part === 1 ? $startYear : $endYear);
    }

    private function invalidateSemesterCaches(): void
    {
        $this->resetLocalCaches();
        AppCache::forgetReportOptionCaches();
        AppCache::bumpLookupVersion();
    }

    private function resetLocalCaches(): void
    {
        $this->configuredSemesterOptionsCache = null;
        $this->closedSupplierSemestersCache = null;
        $this->closedSupplierMonthsCache = null;
        $this->closedCustomerSemestersCache = null;
        $this->activeSemestersCache = null;
        $this->closedSemestersCache = null;
        $this->configuredSemesterMetadataCache = null;
        $this->closedSemesterMetadataCache = null;
        $this->normalizeSemesterCache = [];
        $this->semesterFromDateCache = [];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $collection
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function sortSemesterCollection(\Illuminate\Support\Collection $collection): \Illuminate\Support\Collection
    {
        return $collection
            ->sort(function (string $left, string $right): int {
                return $this->semesterSortKey($left) <=> $this->semesterSortKey($right);
            })
            ->values();
    }

    private function semesterSortKey(string $semester): string
    {
        $normalized = $this->normalizeSemester($semester) ?? strtoupper(trim($semester));
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $normalized, $matches) === 1) {
            $semesterNo = (int) $matches[1];
            $startYear = (int) $matches[2];
            $endYear = (int) $matches[3];

            return sprintf('%02d%02d%d', $startYear, $endYear, $semesterNo);
        }

        return '9999999'.$normalized;
    }
}
