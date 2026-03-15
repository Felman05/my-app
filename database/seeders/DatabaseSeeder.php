<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            ProvinceMunicipalitySeeder::class,
        ]);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Tourist User',
            'email' => 'tourist@doon.local',
            'role' => User::ROLE_TOURIST,
        ]);

        User::factory()->create([
            'name' => 'Provider User',
            'email' => 'provider@doon.local',
            'role' => User::ROLE_PROVIDER,
        ]);

        User::factory()->create([
            'name' => 'LGU User',
            'email' => 'lgu@doon.local',
            'role' => User::ROLE_LGU,
        ]);

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@doon.local',
            'role' => User::ROLE_ADMIN,
        ]);
    }
}
