<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'      => 'PESO Administrator',
                'email'     => 'admin@peso-catanduanes.gov.ph',
                'password'  => Hash::make('password'), // CHANGE IN PRODUCTION
                'is_active' => true,
            ],
            [
                'name'      => 'PESO Staff',
                'email'     => 'staff@peso-catanduanes.gov.ph',
                'password'  => Hash::make('password'), // CHANGE IN PRODUCTION
                'is_active' => true,
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(['email' => $data['email']], $data);
        }
    }
}