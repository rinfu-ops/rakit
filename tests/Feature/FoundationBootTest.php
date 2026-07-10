<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FoundationBootTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_application_redirects_to_authenticated_dashboard_entrypoint(): void
    {
        $this->get('/')
            ->assertRedirect('/dashboard');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard_shell(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('layout-menu menu-vertical menu bg-menu-theme', false)
            ->assertSee('navbar-detached', false)
            ->assertSee('RAKIT dashboard')
            ->assertSee('PostgreSQL')
            ->assertSee('Database');
    }
}
