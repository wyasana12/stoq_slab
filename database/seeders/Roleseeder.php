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

        $menu = [
            'menu_dashboard',
            'menu_monitoring_gudang',
            'menu_monitoring_all',
            'menu_permission',
            'menu_role',
            'menu_user',
            'menu_warehouse',
            'menu_supplier',
            'menu_category',
            'menu_unit',
            'menu_product',
            'menu_purchase',
            'menu_receive',
            'menu_batch',
            'menu_return',
            'menu_restock',
            'menu_distribution',
            'menu_transfer',
            'menu_analisis',
            'menu_barcode',
            'menu_confirm_return',
            'menu_confirm_restock',
            'menu_confirm_transfer',
            'menu_confirm_purchase',
            'menu_confirm_receive',
        ];

        $superadmin = [
            'menu_dashboard',
            'menu_monitoring_all',
            'menu_permission',
            'menu_role',
            'menu_user',
            'menu_warehouse',
            'menu_supplier',
            'menu_category',
            'menu_unit',
            'menu_product',
            'menu_analyst',
            'menu_supplier_product',
            'menu_confirm_return',
            'menu_confirm_restock',
            'menu_confirm_transfer',
            'menu_confirm_purchase',
            'menu_confirm_receive',
            'menu_store',
            'menu_supplier_product',

            // Store
            'view_store',
            'create_store',
            'edit_store',
            'delete_store',

            // Warehouses
            'view_warehouse',
            'create_warehouse',
            'edit_warehouse',
            'delete_warehouse',

            // Suppliers
            'view_supplier',
            'create_supplier',
            'edit_supplier',
            'delete_supplier',

            // Categories
            'view_category',
            'create_category',
            'edit_category',
            'delete_category',

            // Units
            'view_unit',
            'create_unit',
            'edit_unit',
            'delete_unit',

            // Products
            'view_products',
            'create_products',
            'edit_products',
            'delete_products',
            'restore_products',

            // Supplier Products
            'view_supplier_product',
            'create_supplier_product',
            'edit_supplier_product',
            'delete_supplier_product',
            'restore_and_force_supplier_product',

            // Confirmation
            'confirm_purchase',

            'confirm_restock',
            'confirm_distribution',
            'confirm_transfer',
            'confirm_return',

            // Restocks
            'view_restock',

            // Transfers
            'view_transfer'
        ];

        $admin = [
            'menu_dashboard',
            'menu_receive',
            'menu_batch',
            'menu_return',
            'menu_restock',
            'menu_distribution',
            'menu_transfer',
            'menu_analisis',
            'menu_monitoring_gudang',
            'menu_lokasi_rak',
            'menu_purchase',

            // Restocks
            'view_restock',
            'create_restock',
            'edit_restock',
            'delete_restock',

            // Distributions
            'view_distribution',
            'create_distribution',
            'edit_distribution',
            'delete_distribution',

            // Transfers
            'view_transfer',
            'create_transfer',
            'edit_transfer',
            'delete_transfer',

            // PO
            'view_purchase',
            'create_purchase',
            'edit_purchase',
            'delete_purchase',
            'restore_and_force_purchase',

            // Receives
            'view_receive',
            'create_receive',
            'edit_receive',
            'delete_receive',
            'restore_receive',

            // Batches
            'view_batch',
            'generate_barcode',

            'view_product',
            'view_warehouse',

        ];

        $user = [
            'menu_dashboard',
            'menu_distribution_staff',
            'menu_kondisi_barang',
            'menu_kondisi_rak',
            'menu_scan',

            'view_warehouse',
            'view_product',

            // Batches
            'view_batch',
            'edit_batch',
            'scan_barcode',

            // Distributions
            'view_distribution',
            'edit_distribution',
            'confirm_distribution',

            'view_return',
            'create_return',
            'edit_return',
            'delete_return',
        ];

        $permissions = array_unique(array_merge(
            $menu,
            $superadmin,
            $admin,
            $user,
        ));

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $superadminRole = Role::firstOrCreate([
            'name' => RoleName::SuperAdmin->value,
            'guard_name' => 'sanctum',
        ]);

        $adminRole = Role::firstOrCreate([
            'name' => RoleName::Admin->value,
            'guard_name' => 'sanctum',
        ]);

        $staffRole = Role::firstOrCreate([
            'name' => RoleName::Staff->value,
            'guard_name' => 'sanctum',
        ]);

        $superadminRole->syncPermissions($superadmin);

        $adminRole->syncPermissions($admin);

        $staffRole->syncPermissions($user);
    }
}
