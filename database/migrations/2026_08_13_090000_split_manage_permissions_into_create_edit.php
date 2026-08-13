<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Splits each flat `manage_X` permission into `create_X`/`edit_X` (see
 * App\Support\RolePermissions for the current vocabulary) so admins can
 * grant create and edit independently. Anyone who directly held the old
 * permission is granted its new equivalent(s), so nobody's access silently
 * changes on deploy — an admin can narrow individual accounts afterward.
 *
 * No-op on a fresh install: the old permission rows only exist if a
 * previous seeder run created them, which `RolesAndPermissionsSeeder`
 * (already updated to the new vocabulary) no longer does.
 */
return new class extends Migration
{
    private const SPLITS = [
        'manage_products' => ['create_products', 'edit_products'],
        'manage_categories' => ['create_categories', 'edit_categories'],
        'manage_menus' => ['create_menus', 'edit_menus'],
        'manage_cms_pages' => ['create_cms_pages', 'edit_cms_pages'],
        'manage_media' => ['create_media', 'edit_media'],
        'manage_theme' => ['edit_theme'],
        'manage_discounts' => ['create_discounts', 'edit_discounts'],
        'manage_reviews' => ['edit_reviews'],
        'manage_staff' => ['create_staff', 'edit_staff'],
    ];

    public function up(): void
    {
        foreach (self::SPLITS as $old => $replacements) {
            $oldPermission = Permission::where('name', $old)->first();

            if (! $oldPermission) {
                continue;
            }

            $holders = DB::table('model_has_permissions')
                ->where('permission_id', $oldPermission->id)
                ->get(['model_id', 'model_type']);

            foreach ($replacements as $newName) {
                $newPermission = Permission::findOrCreate($newName);

                foreach ($holders as $holder) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $newPermission->id,
                        'model_id' => $holder->model_id,
                        'model_type' => $holder->model_type,
                    ]);
                }
            }

            // Cascades to that permission's model_has_permissions rows.
            $oldPermission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * One-way: recombining create_X/edit_X back into manage_X would need
     * to know which users had create vs edit vs both, which isn't
     * reliably recoverable once the grants have potentially diverged.
     */
    public function down(): void {}
};
