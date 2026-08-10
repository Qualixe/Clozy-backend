<?php

namespace App\Providers;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
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

        // Brands the verification email with the logo/accent color/footer
        // text configured in the dashboard's Settings > Email tab, instead
        // of Laravel's plain default template.
        VerifyEmail::toMailUsing(function (User $notifiable, string $url) {
            $settings = StoreSetting::current();

            return (new MailMessage)
                ->subject('Verify your email address')
                ->view('emails.verify', [
                    'url' => $url,
                    'logoUrl' => $settings->email_logo_url,
                    'accentColor' => $settings->email_accent_color ?: '#111827',
                    'footerText' => $settings->email_footer_text,
                    'appName' => config('app.name'),
                ]);
        });
    }
}
