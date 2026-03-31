<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->after('name');
        });

        $existingUsers = DB::table('users')
            ->select(['id', 'name', 'email', 'username'])
            ->orderBy('id')
            ->get();

        $usedUsernames = [];

        foreach ($existingUsers as $user) {
            $currentUsername = trim((string) ($user->username ?? ''));
            if ($currentUsername !== '') {
                $usedUsernames[] = strtolower($currentUsername);
                continue;
            }

            $baseUsername = trim((string) Str::of((string) ($user->email ?? ''))
                ->before('@')
                ->lower()
                ->replaceMatches('/[^a-z0-9._-]+/', ''));

            if ($baseUsername === '') {
                $baseUsername = trim((string) Str::of((string) ($user->name ?? ''))
                    ->lower()
                    ->replaceMatches('/[^a-z0-9._-]+/', '-')
                    ->trim('-'));
            }

            if ($baseUsername === '') {
                $baseUsername = 'user';
            }

            $candidate = $baseUsername;
            $suffix = 1;
            while (in_array(strtolower($candidate), $usedUsernames, true)) {
                $suffix++;
                $candidate = $baseUsername.'-'.$suffix;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['username' => $candidate]);

            $usedUsernames[] = strtolower($candidate);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username', 'users_username_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
    }
};
