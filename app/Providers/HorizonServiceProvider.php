<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        if (($webhook = config('horizon.notifications.slack.webhook_url')) !== null) {
            Horizon::routeSlackNotifications($webhook, config('horizon.notifications.slack.channel'));
        }
    }

    protected function authorization(): void
    {
        Horizon::auth(function ($request) {
            $guard = config('horizon.guard', config('auth.defaults.guard'));

            $user = $request->user($guard);

            if ($user === null) {
                return false;
            }

            return Gate::forUser($user)->allows('viewHorizon');
        });
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user): bool {
            $allowed = config('horizon.allowed_emails', []);

            if (empty($allowed)) {
                return method_exists($user, 'isAdmin') ? (bool) $user->isAdmin() : false;
            }

            $email = (string) ($user->email ?? '');

            return in_array(Str::lower($email), array_map('strtolower', $allowed), true);
        });
    }
}
