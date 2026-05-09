<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
         $this->call([
            MunicipalitySeeder::class,
            BarangaySeeder::class,
            SkillCategorySeeder::class,
            SkillSeeder::class,
            // AdminUserSeeder::class,
            RoleSeeder::class,

        ]);

    }
}
