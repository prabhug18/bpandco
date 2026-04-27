<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Granular Permissions
        $permissions = [
            'view dashboard',
            'manage users',
            'manage roles',
            'configure metrics',
            'configure scoring tiers',
            'configure holidays',
            'submit slips',
            'approve slips',
            'view own reports',
            'view team reports',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // 2. Create Roles
        $adminRole = Role::findOrCreate('admin');
        $supervisorRole = Role::findOrCreate('supervisor');
        $userRole = Role::findOrCreate('user');

        // 3. Assign Permissions to Roles
        // Admin gets everything
        $adminRole->givePermissionTo(Permission::all());

        // Supervisor gets team management and own submission
        $supervisorRole->syncPermissions([
            'view dashboard',
            'submit slips',
            'approve slips',
            'view own reports',
            'view team reports'
        ]);

        // User gets only basics
        $userRole->syncPermissions([
            'view dashboard',
            'submit slips',
            'view own reports'
        ]);

        // 4. Create Default Target Users
        // Admin User
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );
        $adminUser->assignRole($adminRole);

        // Supervisor User
        $supervisorUser = User::firstOrCreate(
            ['email' => 'supervisor@example.com'],
            [
                'name' => 'Test Supervisor',
                'password' => Hash::make('password'),
            ]
        );
        $supervisorUser->assignRole($supervisorRole);

        // Standard User
        $standardUser = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ]
        );
        $standardUser->assignRole($userRole);

        $this->command->info('Roles created: admin, supervisor, user');
        $this->command->info('Test users created (password for all is "password"):');
        $this->command->info('- admin@example.com');
        $this->command->info('- supervisor@example.com');
        $this->command->info('- user@example.com');
    }
}
