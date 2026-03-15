<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_access_its_dashboard(): void
    {
        $tourist = User::factory()->create(['role' => User::ROLE_TOURIST]);
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);
        $lgu = User::factory()->create(['role' => User::ROLE_LGU]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($tourist)->get(route('tourist.dashboard'))->assertOk();
        $this->actingAs($provider)->get(route('provider.dashboard'))->assertOk();
        $this->actingAs($lgu)->get(route('lgu.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_map_endpoint_responds_for_core_status_filters(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TOURIST]);

        $this->actingAs($user)->getJson(route('api.map.points', ['status' => 'approved']))->assertOk();
        $this->actingAs($user)->getJson(route('api.map.points', ['status' => 'pending']))->assertOk();
    }

    public function test_authenticated_user_is_redirected_away_from_landing_page(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TOURIST]);

        $this->actingAs($user)
            ->get(route('landing'))
            ->assertRedirect(route('dashboard'));
    }
}
