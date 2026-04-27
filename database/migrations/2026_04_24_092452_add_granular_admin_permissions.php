<?php
 
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
 
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            'configure incentives',
            'manage system settings'
        ];
 
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
 
        // Assign to Admin role by default
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('name', ['configure incentives', 'manage system settings'])->delete();
    }
};
