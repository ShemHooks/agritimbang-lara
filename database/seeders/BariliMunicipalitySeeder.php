<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;

class BariliMunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        Municipality::updateOrCreate(
            ['psgc_code' => '0702210000'],
            [
                'name' => 'Barili',
                'province' => 'Cebu',
                'is_active' => true,
            ]
        );
    }
}
