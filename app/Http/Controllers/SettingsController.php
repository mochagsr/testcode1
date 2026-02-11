<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\SemesterBookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SemesterBookService $semesterBookService
    ) {
    }

    public function edit(): View
    {
        $rawSemesterOptions = (string) AppSetting::getValue('semester_period_options', '');
        $semesterPeriodCollection = collect(preg_split('/[\r\n,]+/', $rawSemesterOptions) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique();
        $semesterPeriodOptions = $semesterPeriodCollection->implode(PHP_EOL);
        $currentSemester = $this->semesterBookService->currentSemester();
        $previousSemester = $this->semesterBookService->previousSemester($currentSemester);
        $closedSemesters = collect($this->semesterBookService->closedSemesters());
        $semesterBookOptions = $semesterPeriodCollection
            ->merge([$currentSemester, $previousSemester])
            ->merge($closedSemesters)
            ->unique()
            ->sortDesc()
            ->values();
        $selectedActiveSemesters = collect($this->semesterBookService->activeSemesters())
            ->intersect($semesterBookOptions)
            ->values();
        if ($selectedActiveSemesters->isEmpty()) {
            $selectedActiveSemesters = $semesterBookOptions->values();
        }
        $rawUnitOptions = (string) AppSetting::getValue('product_unit_options', 'exp|Exemplar');
        $unitOptions = $this->normalizeUnitOptions($rawUnitOptions)
            ->map(fn (array $item): string => $item['code'].'|'.$item['label'])
            ->implode(PHP_EOL);

        return view('settings.edit', [
            'user' => auth()->user(),
            'companyLogoPath' => AppSetting::getValue('company_logo_path'),
            'companyName' => AppSetting::getValue('company_name', 'CV. PUSTAKA GRAFIKA'),
            'companyAddress' => AppSetting::getValue('company_address', ''),
            'companyPhone' => AppSetting::getValue('company_phone', ''),
            'companyEmail' => AppSetting::getValue('company_email', ''),
            'companyNotes' => AppSetting::getValue('company_notes', ''),
            'companyInvoiceNotes' => AppSetting::getValue('company_invoice_notes', ''),
            'companyBillingNote' => AppSetting::getValue('company_billing_note', ''),
            'companyTransferAccounts' => AppSetting::getValue('company_transfer_accounts', ''),
            'semesterPeriodOptions' => $semesterPeriodOptions,
            'unitOptions' => $unitOptions,
            'semesterBookOptions' => $semesterBookOptions,
            'closedSemesters' => $closedSemesters->values(),
            'selectedActiveSemesters' => $selectedActiveSemesters,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'in:id,en'],
            'theme' => ['required', 'in:light,dark'],
            'password' => ['nullable', 'string', 'min:6'],
            'company_logo' => ['nullable', 'image', 'max:2048'],
            'remove_company_logo' => ['nullable', 'boolean'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:120'],
            'company_email' => ['nullable', 'string', 'max:120'],
            'company_notes' => ['nullable', 'string', 'max:500'],
            'company_invoice_notes' => ['nullable', 'string', 'max:2000'],
            'company_billing_note' => ['nullable', 'string', 'max:2000'],
            'company_transfer_accounts' => ['nullable', 'string', 'max:2000'],
            'semester_period_options' => ['nullable', 'string', 'max:2000'],
            'semester_active_periods' => ['nullable', 'array'],
            'semester_active_periods.*' => ['nullable', 'string', 'max:30'],
            'product_unit_options' => ['nullable', 'string', 'max:2000'],
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

            $rawSemesterOptions = (string) ($data['semester_period_options'] ?? '');
            $normalizedSemesterOptions = collect(preg_split('/[\r\n,]+/', $rawSemesterOptions) ?: [])
                ->map(fn (string $item): string => trim($item))
                ->map(fn (string $item): ?string => $this->semesterBookService->normalizeSemester($item))
                ->filter(fn (?string $item): bool => $item !== null)
                ->unique()
                ->sortDesc()
                ->implode(',');
            $normalizedActiveSemesters = collect($request->input('semester_active_periods', []))
                ->map(fn ($item): string => trim((string) $item))
                ->map(fn (string $item): ?string => $this->semesterBookService->normalizeSemester($item))
                ->filter(fn (?string $item): bool => $item !== null)
                ->unique()
                ->sortDesc()
                ->implode(',');
            $normalizedUnitOptions = $this->normalizeUnitOptions((string) ($data['product_unit_options'] ?? ''))
                ->map(fn (array $item): string => $item['code'].'|'.$item['label'])
                ->implode(',');
            AppSetting::setValue('semester_period_options', $normalizedSemesterOptions);
            AppSetting::setValue('semester_active_periods', $normalizedActiveSemesters);
            AppSetting::setValue('product_unit_options', $normalizedUnitOptions);
            AppSetting::setValue('product_default_unit', 'exp');
            AppSetting::setValue('company_name', trim((string) ($data['company_name'] ?? '')));
            AppSetting::setValue('company_address', trim((string) ($data['company_address'] ?? '')));
            AppSetting::setValue('company_phone', trim((string) ($data['company_phone'] ?? '')));
            AppSetting::setValue('company_email', trim((string) ($data['company_email'] ?? '')));
            AppSetting::setValue('company_notes', trim((string) ($data['company_notes'] ?? '')));
            AppSetting::setValue('company_invoice_notes', trim((string) ($data['company_invoice_notes'] ?? '')));
            AppSetting::setValue('company_billing_note', trim((string) ($data['company_billing_note'] ?? '')));
            AppSetting::setValue('company_transfer_accounts', trim((string) ($data['company_transfer_accounts'] ?? '')));
        }

        return redirect()->route('settings.edit')->with('success', __('menu.settings_saved'));
    }

    public function closeSemester(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'semester_period' => ['required', 'string', 'max:30'],
        ]);

        $semester = $this->semesterBookService->normalizeSemester((string) ($data['semester_period'] ?? ''));
        if ($semester === null) {
            return redirect()
                ->route('settings.edit')
                ->withErrors(['semester_period' => __('ui.invalid_semester_format')]);
        }

        $this->semesterBookService->closeSemester($semester);

        return redirect()
            ->route('settings.edit')
            ->with('success', __('ui.semester_closed_success', ['semester' => $semester]));
    }

    public function openSemester(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'semester_period' => ['required', 'string', 'max:30'],
        ]);

        $semester = $this->semesterBookService->normalizeSemester((string) ($data['semester_period'] ?? ''));
        if ($semester === null) {
            return redirect()
                ->route('settings.edit')
                ->withErrors(['semester_period' => __('ui.invalid_semester_format')]);
        }

        $this->semesterBookService->openSemester($semester);

        return redirect()
            ->route('settings.edit')
            ->with('success', __('ui.semester_opened_success', ['semester' => $semester]));
    }

    private function normalizeUnitOptions(string $raw): \Illuminate\Support\Collection
    {
        $options = collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(function (string $item): array {
                [$code, $label] = array_pad(array_map('trim', explode('|', $item, 2)), 2, '');
                $normalizedCode = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', $code));
                $normalizedLabel = $label !== '' ? $label : ucfirst($normalizedCode);

                return [
                    'code' => $normalizedCode,
                    'label' => $normalizedLabel,
                ];
            })
            ->filter(fn (array $item): bool => $item['code'] !== '')
            ->unique('code')
            ->values();

        $withoutExp = $options->filter(fn (array $item): bool => $item['code'] !== 'exp')->values();

        $withDefault = collect([[
            'code' => 'exp',
            'label' => 'Exemplar',
        ]])->merge($withoutExp);

        return $withDefault->values();
    }
}
