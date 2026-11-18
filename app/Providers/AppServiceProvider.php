<?php

namespace App\Providers;

use App\Support\Scheduler;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\User;
use App\Services\ActivityStatusService;
use App\Services\InstagramFeedService;
use App\Services\ProgramRegistrationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActivityStatusService::class);
        $this->app->singleton(ProgramRegistrationService::class);
        $this->app->singleton(InstagramFeedService::class);
        $this->app->singleton(\App\Services\MeritService::class);
        $this->app->singleton(\App\Services\MtService::class);
    }

    public function boot(): void
    {
        // Password reset email in the club's style and wording.
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);

            return (new MailMessage)
                ->subject('Reset your YCC Hub password')
                ->markdown('mail.password-reset', [
                    'name' => method_exists($notifiable, 'firstName') ? $notifiable->firstName() : 'there',
                    'url' => $url,
                    'minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
                ]);
        });

        // No cron job on the server? Site visits keep notifications, reminders and
        // batched emails moving instead, after the page has gone to the browser.
        if (! $this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            $this->app->terminating(fn () => Scheduler::fallback());
        }

        // The installer runs before the sessions/cache tables exist, so it keeps
        // its session and cache in files instead of the database.
        if (! $this->app->runningInConsole() && $this->app['request']->is('install', 'install/*')) {
            config(['session.driver' => 'file', 'cache.default' => 'file']);
        }

        Gate::define('exco', fn (User $user) => $user->isApproved() && $user->isExco());
        Gate::define('admin', fn (User $user) => $user->isApproved() && $user->isAdmin());
        Gate::define('superadmin', fn (User $user) => $user->isApproved() && $user->isSuperadmin());
        // See the MT pages: MT, superadmin, and advisors (read-only, enforced by the advisor.readonly middleware).
        Gate::define('view-mt', fn (User $user) => $user->isApproved() && ($user->isAdmin() || $user->isAdvisor()));
        Gate::define('view-activity', fn (User $user) => $user->isApproved() && ($user->isSuperadmin() || $user->isAdvisor()));

        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('login')).'|'.$request->ip();

            return [Limit::perMinute(5)->by($key), Limit::perMinute(30)->by($request->ip())];
        });
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        Paginator::defaultView('partials.pagination');

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
