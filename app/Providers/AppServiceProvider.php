<?php

namespace App\Providers;

use App\Listeners\HandleClientAck;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Events\MessageReceived;

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
        View::share('viteAvailable', file_exists(public_path('build/manifest.json'))
            || file_exists(public_path('hot')));

        Event::listen(
            MessageReceived::class,
            HandleClientAck::class
        );

        Broadcast::routes(['prefix' => 'api', 'guards' => 'websocket']);

        // Custom guard driver that validates the Bearer token
        Auth::extend('websocket', function ($app, $name, array $config) {
            return new \App\Auth\WebsocketGuard(
                Auth::createUserProvider($config['provider']),
                request()
            );
        });

        require base_path('routes/channels.php');
    }
}
