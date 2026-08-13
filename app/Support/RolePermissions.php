<?php

namespace App\Support;

/**
 * The full permission vocabulary and each role's default starting set.
 *
 * Permissions are granted per-user (direct `model_has_permissions` rows),
 * not at the role level — Spatie permissions are additive (role-granted OR
 * direct), so there's no way to revoke a role-granted permission for a
 * single staff member. Roles here are just labels + a default checkbox
 * preset (see Theme > Users > Edit); the user's own direct grants are what
 * actually get checked.
 *
 * Most resources split into `create_X` (make a new one) and `edit_X`
 * (update or delete an existing one) rather than one flat `manage_X`, so an
 * admin can e.g. let someone edit product listings without letting them
 * publish new ones. Two resources don't have a staff-facing create action
 * at all (theme sections and reviews are never "created" from the
 * dashboard — reviews come from customers, theme sections are singletons
 * that only ever get updated), so they stay a single `edit_X` permission.
 */
class RolePermissions
{
    public const ALL = [
        'view_products',
        'create_products',
        'edit_products',
        'view_categories',
        'create_categories',
        'edit_categories',
        'view_orders',
        'manage_orders',
        'view_discounts',
        'create_discounts',
        'edit_discounts',
        'view_reviews',
        'edit_reviews',
        'view_analytics',
        'view_menus',
        'create_menus',
        'edit_menus',
        'view_media',
        'create_media',
        'edit_media',
        'view_cms_pages',
        'create_cms_pages',
        'edit_cms_pages',
        'edit_theme',
        'view_sms',
        'manage_sms',
        'manage_settings',
        'view_staff',
        'create_staff',
        'edit_staff',
    ];

    /** Everything except staff management and store settings. */
    private const OPERATIONAL = [
        'view_products',
        'create_products',
        'edit_products',
        'view_categories',
        'create_categories',
        'edit_categories',
        'view_orders',
        'manage_orders',
        'view_discounts',
        'create_discounts',
        'edit_discounts',
        'view_reviews',
        'edit_reviews',
        'view_analytics',
        'view_menus',
        'create_menus',
        'edit_menus',
        'view_media',
        'create_media',
        'edit_media',
        'view_cms_pages',
        'create_cms_pages',
        'edit_cms_pages',
        'edit_theme',
        'view_sms',
        'manage_sms',
    ];

    /** Sensible starting checkbox state when a user is created or switched to this role. */
    public static function defaultsFor(string $role): array
    {
        return match ($role) {
            'admin', 'staff' => self::OPERATIONAL,
            default => [],
        };
    }
}
