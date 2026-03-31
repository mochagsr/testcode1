<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_opening_login_page_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_ignores_login_as_intended_destination_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'username' => 'admin-example',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->withSession([
            'url.intended' => route('login'),
        ])->post(route('login.post'), [
            'login' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_accepts_username_as_identifier(): void
    {
        $user = User::factory()->create([
            'username' => 'operator-user',
            'email' => 'operator@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post(route('login.post'), [
            'login' => 'operator-user',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
