<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'user' => auth()->user(),
            'companyLogoPath' => AppSetting::getValue('company_logo_path'),
            'semesterPeriodOptions' => AppSetting::getValue('semester_period_options', ''),
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
            'semester_period_options' => ['nullable', 'string', 'max:2000'],
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
                ->filter(fn (string $item): bool => $item !== '')
                ->unique()
                ->sortDesc()
                ->implode(',');
            AppSetting::setValue('semester_period_options', $normalizedSemesterOptions);
        }

        return redirect()->route('settings.edit')->with('success', __('menu.settings_saved'));
    }
}
