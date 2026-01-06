<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Laravel\Reverb\Events\MessageReceived;
use App\Listeners\HandleClientAck;

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
