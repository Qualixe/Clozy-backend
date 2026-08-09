<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens `users.role` from admin/editor/user to owner/admin/staff/user for
 * the Shopify-style RBAC rollout (owner/admin/staff dashboard roles, `user`
 * unchanged for storefront customers). Existing `admin` rows become `owner`
 * and `editor` rows become `staff` first, so the enum can be narrowed
 * afterwards without leaving orphaned values.
 *
 * Uses raw SQL rather than Schema::table()->change() because MODIFYing an
 * enum column requires doctrine/dbal, which this project doesn't have
 * installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'editor', 'user', 'owner', 'staff') NOT NULL DEFAULT 'user'");
        DB::statement("UPDATE users SET role = 'owner' WHERE role = 'admin'");
        DB::statement("UPDATE users SET role = 'staff' WHERE role = 'editor'");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'admin', 'staff', 'user') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'admin', 'staff', 'user', 'editor') NOT NULL DEFAULT 'user'");
        DB::statement("UPDATE users SET role = 'admin' WHERE role = 'owner'");
        DB::statement("UPDATE users SET role = 'editor' WHERE role = 'staff'");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'editor', 'user') NOT NULL DEFAULT 'user'");
    }
};
