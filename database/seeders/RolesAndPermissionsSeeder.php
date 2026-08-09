<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shopify-style RBAC: owner / admin (collaborator) / staff. Permissions are
 * granted per-user, not per-role — see App\Support\RolePermissions for why.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Roles/permissions are cached — clear it first so this seeder is
        // safe to re-run without picking up stale in-memory state.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RolePermissions::ALL as $permission) {
            Permission::findOrCreate($permission);
        }

        $owner = Role::findOrCreate('owner');
        $admin = Role::findOrCreate('admin');
        $staff = Role::findOrCreate('staff');

        // Roles carry no permissions of their own (see class docblock) —
        // strip any left over from before this per-user model existed.
        $owner->syncPermissions([]);
        $admin->syncPermissions([]);
        $staff->syncPermissions([]);

        // Owner bypasses all permission checks (see AppServiceProvider's
        // Gate::before), so it doesn't need explicit permissions assigned.
        $this->assignRoleAndDefaults('admin@clozy.com', $owner);
        $this->assignRoleAndDefaults('editor@clozy.com', $admin);
    }

    private function assignRoleAndDefaults(string $email, Role $role): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        $user->syncRoles([$role]);
        $user->syncPermissions(RolePermissions::defaultsFor($role->name));
    }
}
