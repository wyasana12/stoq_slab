<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Region;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superRegion = Region::inRandomOrder()->first();

        $superadmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'phone_number' => '081456000003',
            'region_id' => $superRegion,
            'password' => bcrypt('password'),
            'warehouse_id' => null,
        ]);
        $superadmin->assignRole(RoleName::SuperAdmin->value);

        $warehouses = Warehouse::all();

        foreach ($warehouses as $index => $w) {
            $num = $index + 1;

            $admin = User::create([
                'name' => "Admin Gudang $num",
                'username' => "admin.gudang$num",
                'email' => "admin$num@example.com",
                'phone_number' => "08156700002$num",
                'region_id' => $superRegion,
                'password' => bcrypt('password'),
                'warehouse_id' => $w->id,
            ]);
            $admin->assignRole(RoleName::Admin->value);

            $staff = User::create([
                'name' => "Staff Gudang $num",
                'username' => "staff.gudang$num",
                'email' => "staff$num@example.com",
                'phone_number' => "08189100000$num",
                'region_id' => $superRegion,
                'password' => bcrypt('password'),
                'warehouse_id' => $w->id,
            ]);
            $staff->assignRole(RoleName::Staff->value);
        }
    }
}
