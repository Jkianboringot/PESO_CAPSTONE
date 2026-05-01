<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
                Role::insert([
            ['name' => 'Staff',         'slug' => 'staff',         'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Administrator', 'slug' => 'admin',         'created_at' => now(), 'updated_at' => now()],
        ]);

    }
}
