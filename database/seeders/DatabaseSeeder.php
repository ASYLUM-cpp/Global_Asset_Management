<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -- 1. Roles & Permissions --

        $roles = ['Admin', 'Food Team', 'Media Team', 'Marketing Team'];
        foreach ($roles as $r) {
            Role::findOrCreate($r, 'web');
        }

        $permNames = [
            'upload_assets', 'approve_assets', 'reject_assets', 'delete_assets',
            'manage_collections', 'configure_pipeline', 'manage_users',
            'system_settings', 'view_audit_log', 'export_data',
        ];
        foreach ($permNames as $p) {
            Permission::findOrCreate($p, 'web');
        }

        Role::findByName('Admin')->syncPermissions($permNames);
        Role::findByName('Food Team')->syncPermissions(['upload_assets']);
        Role::findByName('Media Team')->syncPermissions(['upload_assets']);
        Role::findByName('Marketing Team')->syncPermissions(['upload_assets']);

        // -- 2. Users --

        $admin = User::updateOrCreate(
            ['email' => 'ali@company.com'],
            ['name' => 'Muhammad Ali', 'password' => Hash::make('password')]
        );
        $admin->assignRole('Admin');

        $foodUser = User::updateOrCreate(
            ['email' => 'sara@company.com'],
            ['name' => 'Sara Ahmed', 'password' => Hash::make('password')]
        );
        $foodUser->assignRole('Food Team');

        $mediaUser = User::updateOrCreate(
            ['email' => 'james@company.com'],
            ['name' => 'James Wilson', 'password' => Hash::make('password')]
        );
        $mediaUser->assignRole('Media Team');

        $marketingUser = User::updateOrCreate(
            ['email' => 'maria@company.com'],
            ['name' => 'Maria Lopez', 'password' => Hash::make('password')]
        );
        $marketingUser->assignRole('Marketing Team');

        // -- 3. Taxonomy --
        $this->call(TaxonomySeeder::class);
    }
}
