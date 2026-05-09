<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            //    $adminRole = Role::where('slug', 'admin')->firstOrFail();
 
        User::create([[
            'name'      => 'PESO Administrator',
            'email'     => 'admin@peso-catanduanes.gov.ph',
            'password'  => Hash::make('password'), // CHANGE IN PRODUCTION
            // 'role_id'   => $adminRole->id,
            'is_active' => true,
        ],
    [
            'name'      => 'PESO Staff',
            'email'     => 'staff@peso-catanduanes.gov.ph',
            'password'  => Hash::make('password'), // CHANGE IN PRODUCTION
            // 'role_id'   => $adminRole->id,
            'is_active' => true,
        ]]);
    }

    
}
