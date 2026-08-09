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
 */
class RolePermissions
{
    public const ALL = [
        'manage_products',
        'manage_categories',
        'view_orders',
        'manage_orders',
        'manage_discounts',
        'view_reviews',
        'manage_reviews',
        'view_analytics',
        'manage_menus',
        'manage_media',
        'manage_cms_pages',
        'manage_theme',
        'view_sms',
        'manage_sms',
        'manage_settings',
        'manage_staff',
    ];

    /** Everything except staff management and store settings. */
    private const OPERATIONAL = [
        'manage_products',
        'manage_categories',
        'view_orders',
        'manage_orders',
        'manage_discounts',
        'view_reviews',
        'manage_reviews',
        'view_analytics',
        'manage_menus',
        'manage_media',
        'manage_cms_pages',
        'manage_theme',
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
