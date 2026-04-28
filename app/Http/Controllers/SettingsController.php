<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Support\AppCache;
use App\Support\SemesterBookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use SanderMuller\FluentValidation\FluentRule;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SemesterBookService $semesterBookService
    ) {}

    public function edit(Request $request): View
    {
        $settings = AppSetting::getValues([
            'semester_period_options' => '',
            'product_unit_options' => 'exp|Exemplar',
            'outgoing_unit_options' => 'exp|Exemplar',
            'company_logo_path' => null,
            'company_name' => 'CV. PUSTAKA GRAFIKA',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'company_notes' => '',
            'company_invoice_notes' => '',
            'company_billing_note' => '',
            'company_transfer_accounts' => '',
            'report_header_text' => '',
            'print_workflow_mode' => 'browser',
            'print_paper_preset' => 'auto',
            'print_small_rows_threshold' => '35',
        ]);

        $rawSemesterOptions = (string) ($settings['semester_period_options'] ?? '');
        $semesterPeriodCollection = collect(preg_split('/[\r\n,]+/', $rawSemesterOptions) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique();
        $currentSemester = $this->semesterBookService->currentSemester();
        $previousSemester = $this->semesterBookService->previousSemester($currentSemester);
        $closedSemesters = collect($this->semesterBookService->closedSemesters());
        $semesterBookOptions = $this->semesterBookService->sortSemesterCollection(
            $semesterPeriodCollection
                ->merge([$currentSemester, $previousSemester])
                ->merge($closedSemesters)
                ->unique()
                ->values()
        );
        $semesterBookPage = max(1, (int) $request->integer('semester_book_page', 1));
        $semesterBookPerPage = 10;
        $semesterBookPaginator = new LengthAwarePaginator(
            $semesterBookOptions->forPage($semesterBookPage, $semesterBookPerPage)->values(),
            $semesterBookOptions->count(),
            $semesterBookPerPage,
            $semesterBookPage,
            [
                'path' => $request->url(),
                'pageName' => 'semester_book_page',
                'query' => $request->query(),
            ]
        );
        $selectedActiveSemesters = collect($this->semesterBookService->activeSemesters())
            ->intersect($semesterBookOptions)
            ->values();
        if ($selectedActiveSemesters->isEmpty()) {
            $selectedActiveSemesters = $semesterBookOptions->values();
        }
        $rawUnitOptions = (string) ($settings['product_unit_options'] ?? 'exp|Exemplar');
        $unitOptionRows = $this->normalizeUnitOptions($rawUnitOptions)->values();
        $unitCodeSuggestions = $unitOptionRows
            ->pluck('code')
            ->merge(['exp', 'pcs', 'pack', 'rim', 'box', 'set', 'lusin'])
            ->map(fn (string $item): string => strtolower(trim($item)))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values();
        $rawOutgoingUnitOptions = (string) ($settings['outgoing_unit_options'] ?? 'exp|Exemplar');
        $outgoingUnitOptionRows = $this->normalizeUnitOptions($rawOutgoingUnitOptions)->values();
        $outgoingUnitCodeSuggestions = $outgoingUnitOptionRows
            ->pluck('code')
            ->merge(['exp', 'pcs', 'pack', 'rim', 'box', 'set', 'lusin'])
            ->map(fn (string $item): string => strtolower(trim($item)))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values();

        return view('settings.edit', [
            'user' => auth()->user(),
            'companyLogoPath' => $settings['company_logo_path'] ?? null,
            'companyName' => $settings['company_name'] ?? 'CV. PUSTAKA GRAFIKA',
            'companyAddress' => $settings['company_address'] ?? '',
            'companyPhone' => $settings['company_phone'] ?? '',
            'companyEmail' => $settings['company_email'] ?? '',
            'companyNotes' => $settings['company_notes'] ?? '',
            'companyInvoiceNotes' => $settings['company_invoice_notes'] ?? '',
            'companyBillingNote' => $settings['company_billing_note'] ?? '',
            'companyTransferAccounts' => $settings['company_transfer_accounts'] ?? '',
            'reportHeaderText' => $settings['report_header_text'] ?? '',
            'printWorkflowMode' => $settings['print_workflow_mode'] ?? 'browser',
            'printPaperPreset' => $settings['print_paper_preset'] ?? 'auto',
            'printSmallRowsThreshold' => (int) ((string) ($settings['print_small_rows_threshold'] ?? '35') !== ''
                ? (int) $settings['print_small_rows_threshold']
                : 35),
            'semesterPeriodOptions' => $rawSemesterOptions,
            'unitOptions' => $rawUnitOptions,
            'unitOptionRows' => $unitOptionRows,
            'unitCodeSuggestions' => $unitCodeSuggestions,
            'outgoingUnitOptionRows' => $outgoingUnitOptionRows,
            'outgoingUnitCodeSuggestions' => $outgoingUnitCodeSuggestions,
            'semesterBookOptions' => $semesterBookOptions,
            'closedSemesters' => $closedSemesters->values(),
            'semesterMetadata' => $this->semesterBookService->configuredSemesterMetadata(),
            'closedSemesterMetadata' => $this->semesterBookService->closedSemesterMetadata(),
            'selectedActiveSemesters' => $selectedActiveSemesters,
            'semesterBookPaginator' => $semesterBookPaginator,
            'currentSemester' => $currentSemester,
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'city']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => FluentRule::string()->required()->max(255),
            'locale' => FluentRule::field()->required()->rule('in:id,en'),
            'theme' => FluentRule::field()->required()->rule('in:light,dark'),
            'password' => FluentRule::string()->nullable()->min(6),
            'company_logo' => FluentRule::image()->nullable()->max(2048),
            'remove_company_logo' => FluentRule::boolean()->nullable(),
            'company_name' => FluentRule::string()->nullable()->max(150),
            'company_address' => FluentRule::string()->nullable()->max(255),
            'company_phone' => FluentRule::string()->nullable()->max(120),
            'company_email' => FluentRule::string()->nullable()->max(120),
            'company_notes' => FluentRule::string()->nullable()->max(4000),
            'company_invoice_notes' => FluentRule::string()->nullable()->max(2000),
            'company_billing_note' => FluentRule::string()->nullable()->max(4000),
            'company_transfer_accounts' => FluentRule::string()->nullable()->max(4000),
            'report_header_text' => FluentRule::string()->nullable()->max(2000),
            'print_workflow_mode' => FluentRule::field()->nullable()->rule('in:browser,qz'),
            'print_paper_preset' => FluentRule::field()->nullable()->rule('in:auto,9.5x5.5,9.5x11'),
            'print_small_rows_threshold' => FluentRule::integer()->nullable()->min(5)->max(200),
            'semester_period_options' => FluentRule::string()->nullable()->max(4000),
            'semester_period_codes' => FluentRule::array()->nullable(),
            'semester_period_codes.*' => FluentRule::string()->nullable()->max(30),
            'semester_active_periods' => FluentRule::array()->nullable(),
            'semester_active_periods.*' => FluentRule::string()->nullable()->max(30),
            'semester_active_period_codes' => FluentRule::array()->nullable(),
            'semester_active_period_codes.*' => FluentRule::string()->nullable()->max(30),
            'product_unit_options' => FluentRule::string()->nullable()->max(2000),
            'product_unit_codes' => FluentRule::array()->nullable(),
            'product_unit_codes.*' => FluentRule::string()->nullable()->max(30),
            'product_unit_labels' => FluentRule::array()->nullable(),
            'product_unit_labels.*' => FluentRule::string()->nullable()->max(120),
            'outgoing_unit_codes' => FluentRule::array()->nullable(),
            'outgoing_unit_codes.*' => FluentRule::string()->nullable()->max(30),
            'outgoing_unit_labels' => FluentRule::array()->nullable(),
            'outgoing_unit_labels.*' => FluentRule::string()->nullable()->max(120),
            'outgoing_unit_options' => FluentRule::string()->nullable()->max(2000),
        ]);

        $user = $request->user();
        $payload = [
            'name' => $data['name'],
            'locale' => $data['locale'],
            'theme' => $data['theme'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        $normalizedUnitOptions = $this->normalizeUnitOptionsFromColumns(
            $request->input('product_unit_codes', []),
            $request->input('product_unit_labels', [])
        );
        if ($normalizedUnitOptions->isEmpty()) {
            $normalizedUnitOptions = $this->normalizeUnitOptions((string) ($data['product_unit_options'] ?? ''));
        }

        $normalizedOutgoingUnitOptions = $this->normalizeUnitOptionsFromColumns(
            $request->input('outgoing_unit_codes', []),
            $request->input('outgoing_unit_labels', [])
        );
        if ($normalizedOutgoingUnitOptions->isEmpty()) {
            $normalizedOutgoingUnitOptions = $this->normalizeUnitOptions((string) ($data['outgoing_unit_options'] ?? 'exp|Exemplar'));
        }
        AppSetting::setValues([
            'product_unit_options' => $normalizedUnitOptions
                ->map(fn (array $item): string => $item['code'].'|'.$item['label'])
                ->implode(','),
            'product_default_unit' => 'exp',
            'outgoing_unit_options' => $normalizedOutgoingUnitOptions
                ->map(fn (array $item): string => $item['code'].'|'.$item['label'])
                ->implode(','),
        ]);
        AppCache::bumpLookupVersion();

        if ($user->role === 'admin') {
            $currentLogoPath = AppSetting::getValue('company_logo_path');
            $removeLogo = (bool) ($data['remove_company_logo'] ?? false);

            if ($request->hasFile('company_logo')) {
                if ($currentLogoPath) {
                    Storage::disk('public')->delete($currentLogoPath);
                }

                $newPath = $request->file('company_logo')->store('company', 'public');
                AppSetting::setValue('company_logo_path', $newPath);
            } elseif ($removeLogo && $currentLogoPath) {
                Storage::disk('public')->delete($currentLogoPath);
                AppSetting::setValue('company_logo_path', null);
            }

            $semesterCodeInputs = collect($request->input('semester_period_codes', []))
                ->map(fn ($item): string => trim((string) $item))
                ->filter(fn (string $item): bool => $item !== '');
            if ($semesterCodeInputs->isEmpty()) {
                $semesterCodeInputs = collect(preg_split('/[\r\n,]+/', (string) ($data['semester_period_options'] ?? '')) ?: [])
                    ->map(fn ($item): string => trim((string) $item))
                    ->filter(fn (string $item): bool => $item !== '');
            }

            $normalizedSemesterOptions = $this->semesterBookService->sortSemesterCollection(
                $semesterCodeInputs
                    ->map(fn (string $item): ?string => $this->semesterBookService->normalizeSemester($item))
                    ->filter(fn (?string $item): bool => $item !== null)
                    ->unique()
                    ->values()
            );
            $existingSemesterMetadata = $this->semesterBookService->configuredSemesterMetadata();
            $semesterMetadata = [];
            foreach ($normalizedSemesterOptions as $semesterOption) {
                $semesterMetadata[$semesterOption] = [
                    'created_at' => $existingSemesterMetadata[$semesterOption]['created_at'] ?? now()->format('Y-m-d H:i:s'),
                ];
            }
            $activeSemesterInputs = collect($request->input('semester_active_period_codes', []))
                ->map(fn ($item): string => trim((string) $item))
                ->filter(fn (string $item): bool => $item !== '');
            if ($activeSemesterInputs->isEmpty()) {
                $activeSemesterInputs = collect($request->input('semester_active_periods', []))
                    ->map(fn ($item): string => trim((string) $item))
                    ->filter(fn (string $item): bool => $item !== '');
            }

            $normalizedActiveSemesters = $this->semesterBookService->sortSemesterCollection(
                $activeSemesterInputs
                    ->map(fn (string $item): ?string => $this->semesterBookService->normalizeSemester($item))
                    ->filter(fn (?string $item): bool => $item !== null)
                    ->filter(fn (string $item): bool => $normalizedSemesterOptions->contains($item))
                    ->unique()
                    ->values()
            )->implode(',');
            AppSetting::setValues([
                'semester_period_options' => $normalizedSemesterOptions->implode(','),
                'semester_period_metadata' => json_encode($semesterMetadata, JSON_UNESCAPED_UNICODE),
                'semester_active_periods' => $normalizedActiveSemesters,
                'company_name' => trim((string) ($data['company_name'] ?? '')),
                'company_address' => trim((string) ($data['company_address'] ?? '')),
                'company_phone' => trim((string) ($data['company_phone'] ?? '')),
                'company_email' => trim((string) ($data['company_email'] ?? '')),
                'company_notes' => trim((string) ($data['company_notes'] ?? '')),
                'company_invoice_notes' => trim((string) ($data['company_invoice_notes'] ?? '')),
                'company_billing_note' => trim((string) ($data['company_billing_note'] ?? '')),
                'company_transfer_accounts' => trim((string) ($data['company_transfer_accounts'] ?? '')),
                'report_header_text' => trim((string) ($data['report_header_text'] ?? '')),
                'print_workflow_mode' => (string) ($data['print_workflow_mode'] ?? 'browser'),
                'print_paper_preset' => (string) ($data['print_paper_preset'] ?? 'auto'),
                'print_small_rows_threshold' => (string) max(5, (int) ($data['print_small_rows_threshold'] ?? 35)),
            ]);
            AppCache::forgetReportOptionCaches();
        }

        return redirect()->route('settings.edit')->with('success', __('menu.settings_saved'));
    }

    public function closeSemester(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'semester_period' => FluentRule::string()->required()->max(30),
            'semester_book_page' => FluentRule::integer()->nullable()->min(1),
            'return_to' => FluentRule::string()->nullable()->max(2000),
        ]);

        $semester = $this->semesterBookService->normalizeSemester((string) ($data['semester_period'] ?? ''));
        if ($semester === null) {
            return redirect()
                ->route('settings.edit')
                ->withErrors(['semester_period' => __('ui.invalid_semester_format')]);
        }

        $this->semesterBookService->closeSemester($semester);

        $routeParams = [];
        if (! empty($data['semester_book_page'])) {
            $routeParams['semester_book_page'] = (int) $data['semester_book_page'];
        }

        return $this->settingsRedirectResponse(
            $data['return_to'] ?? null,
            $routeParams,
            __('ui.semester_closed_success', ['semester' => $semester])
        );
    }

    public function openSemester(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'semester_period' => FluentRule::string()->required()->max(30),
            'semester_book_page' => FluentRule::integer()->nullable()->min(1),
            'return_to' => FluentRule::string()->nullable()->max(2000),
        ]);

        $semester = $this->semesterBookService->normalizeSemester((string) ($data['semester_period'] ?? ''));
        if ($semester === null) {
            return redirect()
                ->route('settings.edit')
                ->withErrors(['semester_period' => __('ui.invalid_semester_format')]);
        }

        $this->semesterBookService->openSemester($semester);

        $routeParams = [];
        if (! empty($data['semester_book_page'])) {
            $routeParams['semester_book_page'] = (int) $data['semester_book_page'];
        }

        return $this->settingsRedirectResponse(
            $data['return_to'] ?? null,
            $routeParams,
            __('ui.semester_opened_success', ['semester' => $semester])
        );
    }

    private function settingsRedirectResponse(?string $returnTo, array $routeParams, string $message): RedirectResponse
    {
        $target = trim((string) $returnTo);
        if ($target !== '' && str_starts_with($target, '/')) {
            return redirect($target)->with('success', $message);
        }

        return redirect()
            ->route('settings.edit', $routeParams)
            ->with('success', $message);
    }

    private function normalizeUnitOptions(string $raw): \Illuminate\Support\Collection
    {
        $options = collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(function (string $item): array {
                $rawCode = '';
                $rawLabel = $item;
                if (str_contains($item, '|')) {
                    [$rawCode, $rawLabel] = array_pad(array_map('trim', explode('|', $item, 2)), 2, '');
                }
                $normalizedLabel = trim($rawLabel) !== '' ? trim($rawLabel) : trim($rawCode);
                $normalizedCode = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', (string) $rawCode));
                if ($normalizedCode === '' && $normalizedLabel !== '') {
                    $normalizedCode = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', $normalizedLabel));
                }
                if ($normalizedLabel === '' && $normalizedCode !== '') {
                    $normalizedLabel = ucfirst($normalizedCode);
                }

                return [
                    'code' => $normalizedCode,
                    'label' => $normalizedLabel,
                ];
            })
            ->filter(fn (array $item): bool => $item['code'] !== '' && $item['label'] !== '')
            ->unique('code')
            ->values();

        $withoutExp = $options->filter(fn (array $item): bool => $item['code'] !== 'exp')->values();

        $withDefault = collect([[
            'code' => 'exp',
            'label' => 'Exemplar',
        ]])->merge($withoutExp);

        return $withDefault->values();
    }

    /**
     * @param  array<int, mixed>  $codes
     * @param  array<int, mixed>  $labels
     */
    private function normalizeUnitOptionsFromColumns(array $codes, array $labels): \Illuminate\Support\Collection
    {
        $rows = collect($codes)
            ->values()
            ->map(function ($code, int $index) use ($labels): array {
                $rawCode = trim((string) $code);
                $rawLabel = trim((string) ($labels[$index] ?? ''));

                $normalizedCode = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', $rawCode));
                if ($normalizedCode === '' && $rawLabel !== '') {
                    $normalizedCode = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', $rawLabel));
                }

                $normalizedLabel = trim((string) preg_replace('/\s+/', ' ', $rawLabel));
                if ($normalizedLabel === '' && $normalizedCode !== '') {
                    $normalizedLabel = ucfirst($normalizedCode);
                }

                return [
                    'code' => $normalizedCode,
                    'label' => $normalizedLabel,
                ];
            })
            ->filter(fn (array $item): bool => $item['code'] !== '' && $item['label'] !== '')
            ->unique('code')
            ->values();

        $withoutExp = $rows->filter(fn (array $item): bool => $item['code'] !== 'exp')->values();

        return collect([[
            'code' => 'exp',
            'label' => 'Exemplar',
        ]])->merge($withoutExp)->values();
    }
}
