<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local')) {
            throw new RuntimeException('DemoDatabaseSeeder cannot run in production.');
        }

        $this->call([
            // DemoUsersSeeder::class,
            BariliOctober2026PriceReferenceSeeder::class,
            PSAOfficialPriceReferenceSeeder::class,
            // DemoMarketDataSeeder::class,
        ]);
    }
}
