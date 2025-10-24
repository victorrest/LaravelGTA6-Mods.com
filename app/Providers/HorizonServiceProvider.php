<?php

namespace App\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function ($request) {
            return optional($request->user())->can('viewHorizon');
        });

        if ($slackWebhook = config('services.slack.horizon_webhook')) {
            Horizon::routeSlackNotificationsTo(
                $slackWebhook,
                config('services.slack.horizon_channel', '#horizon')
            );
        }

        if ($mailAddress = config('services.ops.horizon_mail')) {
            Horizon::routeMailNotificationsTo($mailAddress);
        }

        Horizon::tag(function ($job) {
            $tags = method_exists($job, 'tags') ? $job->tags() : [];

            return array_values(array_filter(array_merge(
                Arr::wrap($tags),
                isset($job->queue) ? ['queue:'.$job->queue] : [],
            )));
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if ($this->app->environment('local')) {
                return true;
            }

            if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return true;
            }

            $allowedEmails = array_filter(array_map('trim', explode(',', (string) env('HORIZON_ALLOWED_EMAILS', ''))));

            if ($allowedEmails === []) {
                return false;
            }

            return $user && in_array($user->email, $allowedEmails, true);
        });
    }
}
