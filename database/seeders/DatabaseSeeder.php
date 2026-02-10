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
        User::query()->firstOrCreate([
            'email' => 'admin@pgpos.local',
        ], [
            'name' => 'Admin PgPOS',
            'password' => Hash::make('admin12345'),
            'role' => 'admin',
            'locale' => 'id',
            'theme' => 'light',
            'finance_locked' => false,
        ]);

        User::query()->firstOrCreate([
            'email' => 'user@pgpos.local',
        ], [
            'name' => 'User PgPOS',
            'password' => Hash::make('user12345'),
            'role' => 'user',
            'locale' => 'id',
            'theme' => 'light',
            'finance_locked' => false,
        ]);
    }
}
