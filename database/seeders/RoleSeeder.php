<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //         Role::insert([
        //     ['name' => 'Staff',         'slug' => 'staff',         'created_at' => now(), 'updated_at' => now()],
        //     ['name' => 'Administrator', 'slug' => 'admin',         'created_at' => now(), 'updated_at' => now()],
        // ]);
        $role_admin = Role::create(['name' => 'admin']);
        $permission=Permission::create(['name'=>'manage users']);

        $role_admin->givePermissionTo($permission);

        $user=User::find(1);

        $user->assignRole($role_admin);
    }
}
