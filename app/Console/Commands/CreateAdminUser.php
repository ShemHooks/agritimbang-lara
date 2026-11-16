<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agritimbang:create-admin';

    /**
     * The console command description.
     */
    protected $description = 'Create an administrator account for AgriTimbang';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('AgriTimbang - Create Administrator');
        $this->newLine();

        // Make sure the admin role exists.
        $adminRole = Role::where('slug', 'admin')->first();

        if (!$adminRole) {
            $this->error(
                'Admin role does not exist. Run php artisan db:seed first.'
            );

            return self::FAILURE;
        }

        $firstName = $this->ask('First Name');
        $middleName = $this->ask('Middle Name (optional)');
        $lastName = $this->ask('Last Name');
        $email = strtolower(trim((string) $this->ask('Email')));
        $phoneNumber = trim((string) ($this->ask('Phone Number (optional)') ?? ''));

        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm Password');

        $data = [
            'first_name' => $firstName,
            'middle_name' => $middleName ?: null,
            'last_name' => $lastName,
            'email' => $email,
            'phone_number' => $phoneNumber ?: null,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ];

        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                'unique:users,email',
            ],
            'phone_number' => [
                'nullable',
                'string',
                \App\Support\ContactValidationRules::philippineMobile(),
                'max:13',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        if ($validator->fails()) {
            $this->error('Unable to create administrator.');

            foreach ($validator->errors()->all() as $error) {
                $this->line(" - {$error}");
            }

            return self::FAILURE;
        }

        $user = User::create([
            'first_name' => $firstName,
            'middle_name' => $middleName ?: null,
            'last_name' => $lastName,
            'email' => $email,
            'phone_number' => $phoneNumber ?: null,

            'password' => Hash::make($password),

            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $user->roles()->attach($adminRole->id);

        $this->newLine();
        $this->info('Administrator created successfully.');
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', trim("{$firstName} {$middleName} {$lastName}")],
                ['Email', $email],
                ['Role', $adminRole->name],
                ['Status', 'active'],
                ['Verification', 'verified'],
            ]
        );

        return self::SUCCESS;
    }
}