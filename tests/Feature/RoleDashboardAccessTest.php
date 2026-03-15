<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_tourist_can_access_tourist_dashboard(): void
    {
        $tourist = User::factory()->create(['role' => User::ROLE_TOURIST]);

        $this->actingAs($tourist)
            ->get(route('tourist.dashboard'))
            ->assertOk();
    }

    public function test_provider_cannot_access_tourist_dashboard(): void
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);

        $this->actingAs($provider)
            ->get(route('tourist.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_redirects_to_role_home(): void
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);

        $this->actingAs($provider)
            ->get(route('dashboard'))
            ->assertRedirect(route('provider.dashboard'));
    }
}
