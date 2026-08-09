<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Owner has unrestricted access — bypasses every permission check,
        // including permissions added after this line ships.
        Gate::before(fn (User $user, string $ability) => $user->hasRole('owner') ? true : null);
    }
}
