<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the read-only `view_X` permissions added alongside the existing
 * `create_X`/`edit_X` pairs (see App\Support\RolePermissions) for products,
 * categories, menus, media, CMS pages, discounts, and staff. These are net
 * new — nobody needs one backfilled, since anyone who already holds
 * create_X or edit_X can already reach the resource (the route/nav checks
 * accept any of the three) — this migration just needs the permission rows
 * to exist so they can be looked up and granted at all.
 */
return new class extends Migration
{
    private const NEW_PERMISSIONS = [
        'view_products',
        'view_categories',
        'view_menus',
        'view_media',
        'view_cms_pages',
        'view_discounts',
        'view_staff',
    ];

    public function up(): void
    {
        foreach (self::NEW_PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::NEW_PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
