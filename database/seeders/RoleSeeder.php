<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       // Create roles
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web'
    ]);

    $staffRole = Role::firstOrCreate([
        'name' => 'staff',
        'guard_name' => 'web'
    ]);

    // Admin
    $admin = User::firstOrCreate(
        ['email' => 'admin@peso-catanduanes.gov.ph'],
        [
            'name'      => 'PESO Administrator',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]
    );

    $admin->assignRole($adminRole);

    // Staff
    $staff = User::firstOrCreate(
        ['email' => 'staff@peso-catanduanes.gov.ph'],
        [
            'name'      => 'PESO Staff',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]
    );

    $staff->assignRole($staffRole);
    }
}
