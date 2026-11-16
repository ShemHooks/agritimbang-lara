<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoDatabaseSeeder cannot run in production.');
        }

        $this->call([
            DemoUsersSeeder::class,
            PSAOfficialPriceReferenceSeeder::class,
            BariliOctober2026PriceReferenceSeeder::class,
            DemoMarketDataSeeder::class,
        ]);
    }
}
