<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'catalog.manage' => 'Manage own catalog and variants',
            'inventory.manage' => 'Manage own inventory',
            'orders.fulfill' => 'Fulfill own seller orders',
            'wallet.view' => 'View own wallet',
            'payout.request' => 'Request seller payout',
            'sellers.review' => 'Review seller applications',
            'kyc.review' => 'Review KYC documents',
            'payouts.process' => 'Process manual payouts',
            'orders.oversee' => 'Oversee marketplace orders',
            'audit.view' => 'View audit records',
        ];
        foreach ($permissions as $code => $label) {
            Permission::firstOrCreate(['code' => $code], ['label' => $label]);
        }

        $seller = Role::where('name', 'seller')->first();
        if ($seller) {
            $seller->permissions()->syncWithoutDetaching(Permission::whereIn('code', ['catalog.manage', 'inventory.manage', 'orders.fulfill', 'wallet.view', 'payout.request'])->pluck('id'));
        }
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching(Permission::pluck('id'));
        }
    }
}
