<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class E2ESeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('admin', 'web');

        $staff = User::create([
            'name'     => 'E2E Staff',
            'email'    => 'staff@test.local',
            'password' => Hash::make('password'),
        ]);
        $staff->assignRole('staff');

        User::create([
            'name'     => 'E2E No Role',
            'email'    => 'norole@test.local',
            'password' => Hash::make('password'),
        ]);

        $virac  = Municipality::create(['name' => 'Virac',  'province' => 'Catanduanes']);
        $pandan = Municipality::create(['name' => 'Pandan', 'province' => 'Catanduanes']);

        Barangay::create(['name' => 'San Jose', 'municipality_id' => $virac->id]);
        Barangay::create(['name' => 'Mabini',   'municipality_id' => $pandan->id]);
    }
}
