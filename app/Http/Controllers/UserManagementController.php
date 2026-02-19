<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $users = User::query()
            ->onlyListColumns()
            ->searchKeyword($search)
            ->orderBy('name')
            ->paginate((int) config('pagination.master_per_page', 20))
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'availablePermissions' => $this->availablePermissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:admin,user'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($this->availablePermissions())],
            'locale' => ['required', 'in:id,en'],
            'theme' => ['required', 'in:light,dark'],
            'finance_locked' => ['nullable', 'boolean'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'permissions' => $data['role'] === 'admin' ? ['*'] : array_values(array_unique((array) ($data['permissions'] ?? []))),
            'locale' => $data['locale'],
            'theme' => $data['theme'],
            'finance_locked' => (bool) ($data['finance_locked'] ?? false),
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'availablePermissions' => $this->availablePermissions(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'in:admin,user'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($this->availablePermissions())],
            'locale' => ['required', 'in:id,en'],
            'theme' => ['required', 'in:light,dark'],
            'finance_locked' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'permissions' => $data['role'] === 'admin' ? ['*'] : array_values(array_unique((array) ($data['permissions'] ?? []))),
            'locale' => $data['locale'],
            'theme' => $data['theme'],
            'finance_locked' => (bool) ($data['finance_locked'] ?? false),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')->with('success', 'Cannot delete currently logged-in user.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * @return array<int, string>
     */
    private function availablePermissions(): array
    {
        $allPermissions = (array) config('rbac.permissions', []);

        return array_values(array_filter(array_map(
            static fn ($permission): string => strtolower(trim((string) $permission)),
            $allPermissions
        ), static fn (string $permission): bool => $permission !== '' && $permission !== '*'));
    }
}
