<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in');
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'phase-one-user@example.test',
        ]);

        $this->post('/login', [
            'email' => 'phase-one-user@example.test',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'phase-one-user@example.test',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'phase-one-user@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        RateLimiter::clear('phase-one-user@example.test|127.0.0.1');

        User::factory()->create([
            'email' => 'phase-one-user@example.test',
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->from('/login')->post('/login', [
                'email' => 'phase-one-user@example.test',
                'password' => 'wrong-password',
            ])->assertRedirect('/login');
        }

        $this->from('/login')->post('/login', [
            'email' => 'phase-one-user@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Too many login attempts. Please try again in 1 minutes.',
            ]);

        $this->assertGuest();
    }
}
