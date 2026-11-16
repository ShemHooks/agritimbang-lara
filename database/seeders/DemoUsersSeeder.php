<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo accounts are disabled in production.');
        }

        $municipalityId = DB::table('municipalities')
            ->where('psgc_code', '0702210000')->value('id');

        if (!$municipalityId) {
            throw new RuntimeException('Run MunicipalitySeeder before DemoUsersSeeder.');
        }

        $barangayIds = DB::table('barangays')
            ->where('municipality_id', $municipalityId)
            ->pluck('id', 'name');

        $accounts = [
            [
                'key' => 'admin',
                'first_name' => 'Demo Admin',
                'last_name' => 'Account',
                'email' => 'admin@gmail.com',
                'phone' => '+639170000001',
                'role' => 'admin',
                'barangay' => null,
                'designation' => 'System Administrator',
            ],
            [
                'key' => 'authority',
                'first_name' => 'Demo Authority',
                'last_name' => 'Account',
                'email' => 'authority@gmail.com',
                'phone' => '+639170000002',
                'role' => 'lgu_authority',
                'barangay' => 'Poblacion',
                'designation' => 'Municipal Agriculture Officer / LGU Authority',
            ],
            [
                'key' => 'encoder',
                'first_name' => 'Demo Encoder',
                'last_name' => 'Account',
                'email' => 'encoder@gmail.com',
                'phone' => '+639170000003',
                'role' => 'lgu_encoder',
                'barangay' => 'Poblacion',
                'designation' => 'Market Encoder',
            ],
            [
                'key' => 'encoder2',
                'first_name' => 'Demo Encoder 2',
                'last_name' => 'Account',
                'email' => 'encoder2@gmail.com',
                'phone' => '+639170000003',
                'role' => 'lgu_encoder',
                'barangay' => 'Poblacion',
                'designation' => 'Market Encoder',
            ],
            [
                'key' => 'authority2', 'first_name' => 'Demo Authority 2', 'last_name' => 'Account', 'email' => 'authority2@gmail.com', 'phone' => '+639170000008', 'role' => 'lgu_authority', 'barangay' => 'Poblacion', 'designation' => 'Assistant Municipal Agriculture Officer',
            ],
            [
                'key' => 'encoder3', 'first_name' => 'Demo Encoder 3', 'last_name' => 'Account', 'email' => 'encoder3@gmail.com', 'phone' => '+639170000009', 'role' => 'lgu_encoder', 'barangay' => 'Poblacion', 'designation' => 'Market Encoder',
            ],
            [
                'key' => 'encoder4', 'first_name' => 'Demo Encoder 4', 'last_name' => 'Account', 'email' => 'encoder4@gmail.com', 'phone' => '+639170000010', 'role' => 'lgu_encoder', 'barangay' => 'Poblacion', 'designation' => 'Market Encoder',
            ],
            [
                'key' => 'farmer1',
                'first_name' => 'Demo Farmer',
                'last_name' => 'One',
                'email' => 'farmer1@gmail.com',
                'phone' => '+639170000004',
                'role' => 'farmer',
                'barangay' => 'Mantalongon',
                'designation' => null,
            ],
            [
                'key' => 'farmer2',
                'first_name' => 'Demo Farmer',
                'last_name' => 'Two',
                'email' => 'farmer2@gmail.com',
                'phone' => '+639170000005',
                'role' => 'farmer',
                'barangay' => 'Giloctog',
                'designation' => null,
            ],
            [
                'key' => 'farmer3',
                'first_name' => 'Demo Farmer',
                'last_name' => 'Three',
                'email' => 'farmer3@gmail.com',
                'phone' => '+639170000006',
                'role' => 'farmer',
                'barangay' => 'Azucena',
                'designation' => null,
            ],
            [
                'key' => 'farmer4', 'first_name' => 'Demo Farmer', 'last_name' => 'Four', 'email' => 'farmer4@gmail.com', 'phone' => '+639170000011', 'role' => 'farmer', 'barangay' => 'Poblacion', 'designation' => null,
            ],
            [
                'key' => 'farmer_unverified',
                'first_name' => 'Demo Farmer',
                'last_name' => 'Unverified',
                'email' => 'farmer.unverified@gmail.com',
                'phone' => '+639170000007',
                'role' => 'farmer',
                'barangay' => 'Mantalongon',
                'designation' => null,
                'verification_status' => 'unverified',
            ],
            [ 'key' => 'farmer_unverified2', 'first_name' => 'Demo Farmer', 'last_name' => 'Pending Two', 'email' => 'farmer.pending2@gmail.com', 'phone' => '+639170000012', 'role' => 'farmer', 'barangay' => 'Giloctog', 'designation' => null, 'verification_status' => 'unverified' ],
            [ 'key' => 'farmer_unverified3', 'first_name' => 'Demo Farmer', 'last_name' => 'Pending Three', 'email' => 'farmer.pending3@gmail.com', 'phone' => '+639170000013', 'role' => 'farmer', 'barangay' => 'Azucena', 'designation' => null, 'verification_status' => 'unverified' ],
        ];

        DB::transaction(function () use ($accounts, $municipalityId, $barangayIds) {
            foreach ($accounts as $account) {
                $now = now();
                $roleId = DB::table('roles')->where('slug', $account['role'])->value('id');

                if (!$roleId) {
                    throw new RuntimeException("Missing role [{$account['role']}]. Run RoleSeeder first.");
                }

                $userId = DB::table('users')->where('email', $account['email'])->value('id') ?: (string) Str::uuid();

                DB::table('users')->updateOrInsert(
                    ['email' => $account['email']],
                    [
                        'id' => $userId,
                        'first_name' => $account['first_name'],
                        'middle_name' => null,
                        'last_name' => $account['last_name'],
                        'password' => Hash::make('4bytes123!'),
                        'phone_number' => $account['phone'],
                        'status' => 'active',
                        'verification_status' => $account['verification_status'] ?? 'verified',
                        'email_verified_at' => $now,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );

                if ($account['role'] === 'farmer') {
                    $barangayName = $account['barangay'];
                    $barangayId = $barangayIds[$barangayName] ?? null;
                    if (!$barangayId) {
                        throw new RuntimeException("Missing Barili barangay [{$barangayName}].");
                    }

                    DB::table('farmer_profiles')->updateOrInsert(
                        ['user_id' => $userId],
                        [
                            'id' => DB::table('farmer_profiles')->where('user_id', $userId)->value('id') ?: (string) Str::uuid(),
                            'municipality_id' => $municipalityId,
                            'barangay_id' => $barangayId,
                            'farmer_code' => 'DEMO-' . strtoupper($account['key']),
                            'address' => 'Demo address, ' . $barangayName . ', Barili, Cebu',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }

                if (in_array($account['role'], ['lgu_encoder', 'lgu_authority'], true)) {
                    DB::table('lgu_officer_profiles')->updateOrInsert(
                        ['user_id' => $userId],
                        [
                            'id' => DB::table('lgu_officer_profiles')->where('user_id', $userId)->value('id') ?: (string) Str::uuid(),
                            'municipality_id' => $municipalityId,
                            'station' => 'Municipal Agriculture Office - Barili',
                            'designation' => $account['designation'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        });

        // Attach clearly watermarked, synthetic sample verification images for pending demo farmers.
        $pendingEmails = ['farmer.unverified@gmail.com','farmer.pending2@gmail.com','farmer.pending3@gmail.com'];
        foreach ($pendingEmails as $index => $email) {
            $userId = DB::table('users')->where('email',$email)->value('id');
            if (!$userId) continue;
            $path = 'demo-verification/sample-farmer-' . ($index + 1) . '.svg';
            if (!Storage::disk('private')->exists($path)) {
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="650"><rect width="100%" height="100%" fill="#eef4ed"/><rect x="30" y="30" width="940" height="590" rx="24" fill="white" stroke="#4b7654" stroke-width="5"/><text x="70" y="110" font-family="Arial" font-size="34" fill="#23432f">SAMPLE FARMER VERIFICATION</text><text x="70" y="180" font-family="Arial" font-size="26" fill="#526b58">DEMO DOCUMENT — NOT A REAL GOVERNMENT ID</text><rect x="70" y="240" width="260" height="280" rx="12" fill="#dce9dd"/><circle cx="200" cy="330" r="65" fill="#8ba78d"/><path d="M100 485 Q200 365 300 485" fill="#8ba78d"/><text x="390" y="300" font-family="Arial" font-size="28" fill="#23432f">For interface testing only</text><text x="390" y="355" font-family="Arial" font-size="22" fill="#526b58">Synthetic sample image</text><text x="390" y="410" font-family="Arial" font-size="22" fill="#526b58">No real personal information</text></svg>';
                Storage::disk('private')->put($path, $svg);
            }
            DB::table('user_verification_documents')->updateOrInsert(
                ['user_id'=>$userId,'document_type'=>'Sample verification image'],
                ['id'=>DB::table('user_verification_documents')->where('user_id',$userId)->where('document_type','Sample verification image')->value('id') ?: (string) Str::uuid(), 'document_number'=>'DEMO-SAMPLE-' . ($index + 1), 'file_path'=>$path, 'status'=>'pending', 'rejection_reason'=>null, 'reviewed_by'=>null, 'reviewed_at'=>null, 'created_at'=>now(), 'updated_at'=>now()]
            );
        }

        $this->command?->info('Seeded demo accounts including three unverified farmers with synthetic verification images. Shared password: 4bytes123!');
    }
}
