<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IdempotentRequestMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'idempotent'])->group(function (): void {
            Route::post('/_test/idempotent/error', function () {
                return back()->withErrors(['x' => 'validation error'])->withInput();
            });

            Route::post('/_test/idempotent/success', function () {
                return back()->with('success', 'ok');
            });
        });
    }

    public function test_failed_redirect_does_not_lock_next_submit(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->from('/form-test')->post('/_test/idempotent/error', [
            'foo' => 'bar',
        ]);
        $first->assertRedirect('/form-test');
        $first->assertSessionHasErrors(['x']);
        $first->assertSessionDoesntHaveErrors(['submit']);

        $second = $this->actingAs($user)->from('/form-test')->post('/_test/idempotent/error', [
            'foo' => 'bar',
        ]);
        $second->assertRedirect('/form-test');
        $second->assertSessionHasErrors(['x']);
        $second->assertSessionDoesntHaveErrors(['submit']);
    }

    public function test_successful_submit_blocks_immediate_duplicate(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->from('/form-test')->post('/_test/idempotent/success', [
            'foo' => 'bar',
        ]);
        $first->assertRedirect('/form-test');
        $first->assertSessionHas('success', 'ok');

        $second = $this->actingAs($user)->from('/form-test')->post('/_test/idempotent/success', [
            'foo' => 'bar',
        ]);
        $second->assertRedirect('/form-test');
        $second->assertSessionHasErrors(['submit']);
    }
}
