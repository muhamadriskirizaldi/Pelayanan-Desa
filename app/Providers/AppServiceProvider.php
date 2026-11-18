<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
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
        View::composer('layouts.dashboard', function ($view) {
            if (Auth::check()) {
                $authUser = Auth::user();
                $unreadNotificationsCount = $authUser->unreadNotifications()->count();
                $recentAppNotifications = $authUser->notifications()->take(6)->get();
                $view->with([
                    'unreadNotificationsCount' => $unreadNotificationsCount,
                    'recentAppNotifications' => $recentAppNotifications,
                ]);
            }
        });
    }
}
