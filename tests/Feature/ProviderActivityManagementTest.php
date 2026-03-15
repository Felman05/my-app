<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderActivityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_open_create_activity_page(): void
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);

        $this->actingAs($provider)
            ->get(route('provider.activities.create'))
            ->assertOk();
    }

    public function test_provider_can_submit_activity(): void
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);
        $category = Category::query()->create([
            'name' => 'Attractions',
            'slug' => 'attractions',
            'is_active' => true,
        ]);
        $province = Province::query()->create([
            'name' => 'Batangas',
            'region' => 'CALABARZON',
        ]);
        $municipality = Municipality::query()->create([
            'province_id' => $province->id,
            'name' => 'Batangas City',
        ]);

        $response = $this->actingAs($provider)->post(route('provider.activities.store'), [
            'title' => 'Taal Volcano Tour',
            'description' => 'Guided day tour package',
            'category_id' => $category->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
        ]);

        $response->assertRedirect(route('provider.activities.index'));

        $this->assertDatabaseHas('activities', [
            'provider_id' => $provider->id,
            'title' => 'Taal Volcano Tour',
            'status' => 'pending',
        ]);
    }

    public function test_tourist_cannot_submit_provider_activity(): void
    {
        $tourist = User::factory()->create(['role' => User::ROLE_TOURIST]);

        $this->actingAs($tourist)
            ->post(route('provider.activities.store'), [])
            ->assertForbidden();
    }
}
