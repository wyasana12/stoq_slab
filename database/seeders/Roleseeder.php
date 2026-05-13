<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Roleseeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Permissions
            'view_permission', 'create_permission', 'edit_permission', 'delete_permission',
            
            // Roles
            'view_role', 'create_role', 'edit_role', 'delete_role', 'assign_permissions',
            
            // Users
            'view_user', 'create_user', 'edit_user',
            
            // Warehouses
            'view_warehouse', 'create_warehouse', 'edit_warehouse', 'delete_warehouse',
            
            // Suppliers
            'view_supplier', 'create_supplier', 'edit_supplier', 'delete_supplier',
            
            // Categories
            'view_category', 'create_category', 'edit_category', 'delete_category',
            
            // Units
            'view_unit', 'create_unit', 'edit_unit', 'delete_unit',
            
            // Products
            'view_products', 'create_products', 'edit_products', 'delete_products', 'restore_products',
            
            // Purchases
            'view_purchase', 'create_purchase', 'edit_purchase', 'delete_purchase', 'restore_purchase', 'confirm_purchase',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superadminRole = Role::firstOrCreate(['name' => RoleName::SuperAdmin->value]);
        $adminRole = Role::firstOrCreate(['name' => RoleName::Admin->value]);
        $staffRole = Role::firstOrCreate(['name' => RoleName::Staff->value]);

        $superadminRole->syncPermissions($permissions);
    }
}
