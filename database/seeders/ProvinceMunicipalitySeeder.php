<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceMunicipalitySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $calabarzon = [
            'Batangas' => ['Batangas City', 'Lipa', 'Nasugbu', 'Lemery', 'San Juan'],
            'Laguna' => ['Calamba', 'Santa Rosa', 'San Pablo', 'Los Banos', 'Pagsanjan'],
            'Cavite' => ['Tagaytay', 'Dasmarinas', 'Bacoor', 'Imus', 'Trece Martires'],
            'Rizal' => ['Antipolo', 'Taytay', 'Binangonan', 'Cainta', 'Angono'],
            'Quezon' => ['Lucena', 'Tayabas', 'Sariaya', 'Mauban', 'Real'],
        ];

        foreach ($calabarzon as $provinceName => $municipalities) {
            $province = Province::query()->updateOrCreate(
                ['name' => $provinceName],
                ['region' => 'CALABARZON']
            );

            foreach ($municipalities as $municipalityName) {
                Municipality::query()->updateOrCreate(
                    [
                        'province_id' => $province->id,
                        'name' => $municipalityName,
                    ],
                    []
                );
            }
        }
    }
}
