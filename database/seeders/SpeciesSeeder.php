<?php

namespace Database\Seeders;

use App\Models\Species;
use Illuminate\Database\Seeder;

class SpeciesSeeder extends Seeder
{
    public function run(): void
    {
        $species = [
            [
                'name' => 'Hog',
                'code' => 'HOG',
                'description' => 'Domestic hog or swine.',
                'is_active' => true,
            ],
            [
                'name' => 'Cattle',
                'code' => 'CATTLE',
                'description' => 'Domestic cattle.',
                'is_active' => true,
            ],
            [
                'name' => 'Goat',
                'code' => 'GOAT',
                'description' => 'Domestic goat.',
                'is_active' => true,
            ],
            [
                'name' => 'Carabao',
                'code' => 'CARABAO',
                'description' => 'Philippine water buffalo or carabao.',
                'is_active' => true,
            ],
        ];

        foreach ($species as $item) {
            Species::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}