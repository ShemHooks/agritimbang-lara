<?php

namespace Database\Seeders;

use App\Models\Breed;
use App\Models\Species;
use Illuminate\Database\Seeder;
use RuntimeException;

class BreedSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * HOG
         */
        $this->seedSpecies('HOG', [
            [
                'name' => 'Philippine Native',
                'code' => 'PH_NATIVE',
                'breed_group' => 'native',
                'description' => 'Philippine native hog.',
            ],
            [
                'name' => 'Landrace',
                'code' => 'LANDRACE',
                'breed_group' => 'commercial',
                'description' => 'Commercial Landrace hog.',
            ],
            [
                'name' => 'Large White / Yorkshire',
                'code' => 'LARGE_WHITE',
                'breed_group' => 'commercial',
                'description' => 'Commercial Large White or Yorkshire hog.',
            ],
            [
                'name' => 'Duroc',
                'code' => 'DUROC',
                'breed_group' => 'commercial',
                'description' => 'Commercial Duroc hog.',
            ],
            [
                'name' => 'Pietrain',
                'code' => 'PIETRAIN',
                'breed_group' => 'commercial',
                'description' => 'Commercial Pietrain hog.',
            ],
            [
                'name' => 'Commercial Crossbred',
                'code' => 'COMMERCIAL_CROSS',
                'breed_group' => 'commercial_crossbred',
                'description' => 'Commercial crossbred hog.',
            ],
            [
                'name' => 'Other / Unknown',
                'code' => 'UNKNOWN',
                'breed_group' => 'unknown',
                'description' => 'Breed is unknown or not listed.',
            ],
        ]);

        /*
         * CATTLE
         */
        $this->seedSpecies('CATTLE', [
            [
                'name' => 'Philippine Native',
                'code' => 'PH_NATIVE',
                'breed_group' => 'native',
                'description' => 'Philippine native cattle.',
            ],
            [
                'name' => 'Brahman',
                'code' => 'BRAHMAN',
                'breed_group' => 'brahman',
                'description' => 'Brahman cattle.',
            ],
            [
                'name' => 'Brahman Crossbred',
                'code' => 'BRAHMAN_CROSS',
                'breed_group' => 'crossbred',
                'description' => 'Brahman crossbred cattle.',
            ],
            [
                'name' => 'Other Crossbred',
                'code' => 'CROSSBRED',
                'breed_group' => 'crossbred',
                'description' => 'Other cattle crossbreed.',
            ],
            [
                'name' => 'Other / Unknown',
                'code' => 'UNKNOWN',
                'breed_group' => 'unknown',
                'description' => 'Breed is unknown or not listed.',
            ],
        ]);

        /*
         * GOAT
         */
        $this->seedSpecies('GOAT', [
            [
                'name' => 'Cebu / Philippine Native',
                'code' => 'CEBU_NATIVE',
                'breed_group' => 'native',
                'description' => 'Local native goat type.',
            ],
            [
                'name' => 'Anglo-Nubian',
                'code' => 'ANGLO_NUBIAN',
                'breed_group' => 'improved',
                'description' => 'Anglo-Nubian goat.',
            ],
            [
                'name' => 'Boer',
                'code' => 'BOER',
                'breed_group' => 'improved',
                'description' => 'Boer goat.',
            ],
            [
                'name' => 'Saanen',
                'code' => 'SAANEN',
                'breed_group' => 'improved',
                'description' => 'Saanen goat.',
            ],
            [
                'name' => 'Upgraded / Crossbred',
                'code' => 'UPGRADED',
                'breed_group' => 'crossbred',
                'description' => 'Upgraded or crossbred goat.',
            ],
            [
                'name' => 'Other / Unknown',
                'code' => 'UNKNOWN',
                'breed_group' => 'unknown',
                'description' => 'Breed is unknown or not listed.',
            ],
        ]);

        /*
         * CARABAO
         */
        $this->seedSpecies('CARABAO', [
            [
                'name' => 'Philippine Native / Swamp',
                'code' => 'PH_SWAMP',
                'breed_group' => 'native_swamp',
                'description' => 'Philippine native swamp-type carabao.',
            ],
            [
                'name' => 'Murrah',
                'code' => 'MURRAH',
                'breed_group' => 'murrah',
                'description' => 'Murrah riverine buffalo.',
            ],
            [
                'name' => 'Philippine Carabao × Murrah Crossbred',
                'code' => 'MURRAH_CROSS',
                'breed_group' => 'murrah_cross',
                'description' => 'Crossbred Philippine carabao and Murrah.',
            ],
            [
                'name' => 'Philippine Dairy Carabao',
                'code' => 'PH_DAIRY_CARABAO',
                'breed_group' => 'dairy',
                'description' => 'Philippine dairy carabao type.',
            ],
            [
                'name' => 'Other / Unknown',
                'code' => 'UNKNOWN',
                'breed_group' => 'unknown',
                'description' => 'Breed is unknown or not listed.',
            ],
        ]);
    }

    private function seedSpecies(
        string $speciesCode,
        array $breeds
    ): void {
        $species = Species::query()
            ->where('code', $speciesCode)
            ->first();

        if (!$species) {
            throw new RuntimeException(
                "Species with code [{$speciesCode}] was not found."
            );
        }

        foreach ($breeds as $breed) {
            Breed::updateOrCreate(
                [
                    'species_id' => $species->id,
                    'code' => $breed['code'],
                ],
                [
                    'name' => $breed['name'],
                    'breed_group' => $breed['breed_group'],
                    'description' => $breed['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}