<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_moderation_queue_by_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER, 'name' => 'Trail Provider']);

        $category = Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature',
            'is_active' => true,
        ]);

        $province = Province::query()->create([
            'name' => 'Quezon',
            'region' => 'CALABARZON',
        ]);

        $municipality = Municipality::query()->create([
            'province_id' => $province->id,
            'name' => 'Lucban',
        ]);

        Activity::query()->create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'title' => 'Bamboo Forest Walk',
            'slug' => 'bamboo-forest-walk',
            'status' => 'pending',
        ]);

        Activity::query()->create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'title' => 'Sunset Ridge Route',
            'slug' => 'sunset-ridge-route',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.moderation.activities.index', ['status' => 'approved', 'search' => 'Sunset']));

        $response->assertOk();
        $response->assertSee('Sunset Ridge Route');
        $response->assertDontSee('Bamboo Forest Walk');
    }

    public function test_admin_can_approve_pending_activity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);

        $category = Category::query()->create([
            'name' => 'Attractions',
            'slug' => 'attractions',
            'is_active' => true,
        ]);

        $province = Province::query()->create([
            'name' => 'Laguna',
            'region' => 'CALABARZON',
        ]);

        $municipality = Municipality::query()->create([
            'province_id' => $province->id,
            'name' => 'San Pablo',
        ]);

        $activity = Activity::query()->create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'title' => 'Seven Lakes Day Tour',
            'slug' => 'seven-lakes-day-tour',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.moderation.activities.approve', $activity->id), ['notes' => 'Verified details'])
            ->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'status' => 'approved',
        ]);
    }
}
