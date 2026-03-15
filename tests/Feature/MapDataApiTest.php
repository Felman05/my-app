<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_points_endpoint_returns_activity_points_for_authenticated_users(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TOURIST]);

        $category = Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature',
            'is_active' => true,
        ]);

        $province = Province::query()->create([
            'name' => 'Laguna',
            'region' => 'CALABARZON',
        ]);

        $municipality = Municipality::query()->create([
            'province_id' => $province->id,
            'name' => 'Calamba',
        ]);

        Activity::query()->create([
            'provider_id' => $user->id,
            'category_id' => $category->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'title' => 'Hotspring Trail',
            'slug' => 'hotspring-trail',
            'status' => 'approved',
            'latitude' => 14.2117,
            'longitude' => 121.1653,
        ]);

        $response = $this->actingAs($user)->getJson(route('api.map.points'));

        $response->assertOk();
        $response->assertJsonPath('data.0.title', 'Hotspring Trail');
        $response->assertJsonPath('data.0.category', 'Nature');
    }

    public function test_map_points_endpoint_filters_by_status(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TOURIST]);

        $category = Category::query()->create([
            'name' => 'Adventure',
            'slug' => 'adventure',
            'is_active' => true,
        ]);

        $province = Province::query()->create([
            'name' => 'Batangas',
            'region' => 'CALABARZON',
        ]);

        $municipality = Municipality::query()->create([
            'province_id' => $province->id,
            'name' => 'Nasugbu',
        ]);

        Activity::query()->create([
            'provider_id' => $user->id,
            'category_id' => $category->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'title' => 'Beach Ridge Trek',
            'slug' => 'beach-ridge-trek',
            'status' => 'approved',
            'latitude' => 14.0700,
            'longitude' => 120.6321,
        ]);

        Activity::query()->create([
            'provider_id' => $user->id,
            'category_id' => $category->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'title' => 'Pending Cliff Walk',
            'slug' => 'pending-cliff-walk',
            'status' => 'pending',
            'latitude' => 14.0722,
            'longitude' => 120.6300,
        ]);

        $response = $this->actingAs($user)->getJson(route('api.map.points', ['status' => 'pending']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Pending Cliff Walk');
    }
}
