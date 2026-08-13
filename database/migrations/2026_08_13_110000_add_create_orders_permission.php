<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the `create_orders` permission and requires it for
 * dashboard-created (POS) orders (see OrderController::store()) — before
 * this, that path only checked the bearer token belonged to *some*
 * dashboard-role user, so any staff account, even one with zero
 * permissions, could create real orders.
 *
 * Anyone who already directly holds `manage_orders` is granted
 * `create_orders` too, so staff already trusted with order operations
 * aren't locked out of "Create Order" by this tightening. Accounts with no
 * order-related permission at all get none — that's the gap being closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $createOrders = Permission::findOrCreate('create_orders');

        $manageOrders = Permission::where('name', 'manage_orders')->first();

        if ($manageOrders) {
            $holders = DB::table('model_has_permissions')
                ->where('permission_id', $manageOrders->id)
                ->get(['model_id', 'model_type']);

            foreach ($holders as $holder) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $createOrders->id,
                    'model_id' => $holder->model_id,
                    'model_type' => $holder->model_type,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'create_orders')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
