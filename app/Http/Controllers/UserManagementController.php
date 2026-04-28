<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use SanderMuller\FluentValidation\FluentRule;

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
            'name' => FluentRule::string()->required()->max(255),
            'username' => FluentRule::string()->required()->min(3)->max(50)->regex('/^[A-Za-z0-9._-]+$/')->unique('users', 'username'),
            'email' => FluentRule::email()->required()->max(255)->unique('users', 'email'),
            'password' => FluentRule::string()->required()->min(6),
            'role' => FluentRule::field()->required()->rule('in:admin,user'),
            'permissions' => FluentRule::array()->nullable(),
            'permissions.*' => FluentRule::string()->in($this->availablePermissions()),
            'locale' => FluentRule::field()->required()->rule('in:id,en'),
            'theme' => FluentRule::field()->required()->rule('in:light,dark'),
            'finance_locked' => FluentRule::boolean()->nullable(),
        ]);

        User::create([
            'name' => $data['name'],
            'username' => strtolower(trim((string) $data['username'])),
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
            'name' => FluentRule::string()->required()->max(255),
            'username' => FluentRule::string()->required()->min(3)->max(50)->regex('/^[A-Za-z0-9._-]+$/')->unique('users', 'username', fn ($rule) => $rule->ignore($user->id)),
            'email' => FluentRule::email()->required()->max(255)->unique('users', 'email', fn ($rule) => $rule->ignore($user->id)),
            'password' => FluentRule::string()->nullable()->min(6),
            'role' => FluentRule::field()->required()->rule('in:admin,user'),
            'permissions' => FluentRule::array()->nullable(),
            'permissions.*' => FluentRule::string()->in($this->availablePermissions()),
            'locale' => FluentRule::field()->required()->rule('in:id,en'),
            'theme' => FluentRule::field()->required()->rule('in:light,dark'),
            'finance_locked' => FluentRule::boolean()->nullable(),
        ]);

        $payload = [
            'name' => $data['name'],
            'username' => strtolower(trim((string) $data['username'])),
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
