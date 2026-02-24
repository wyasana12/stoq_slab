<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class Roleseeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superadminRole = Role::firstOrCreate(['name' => RoleName::SuperAdmin->value]);
        $adminRole = Role::firstOrCreate(['name' => RoleName::Admin->value]);
        $staffRole = Role::firstOrCreate(['name' => RoleName::Staff->value]);
    }
}
