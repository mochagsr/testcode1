<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrCreate([
            'email' => 'admin@pgpos.local',
        ], [
            'name' => 'Admin PgPOS',
            'username' => 'admin',
            'password' => Hash::make('@Passwordadmin123#'),
            'role' => 'admin',
            'locale' => 'id',
            'theme' => 'light',
            'finance_locked' => false,
        ]);

        if (blank($admin->username)) {
            $admin->forceFill(['username' => 'admin'])->save();
        }

        $user = User::query()->firstOrCreate([
            'email' => 'user@pgpos.local',
        ], [
            'name' => 'User PgPOS',
            'username' => 'user',
            'password' => Hash::make('@Passworduser123#'),
            'role' => 'user',
            'locale' => 'id',
            'theme' => 'light',
            'finance_locked' => false,
        ]);

        if (blank($user->username)) {
            $user->forceFill(['username' => 'user'])->save();
        }
    }
}
