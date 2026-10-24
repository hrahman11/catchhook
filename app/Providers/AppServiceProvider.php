<?php

namespace App\Providers;

use App\Services\Replay\DnsHostResolver;
use App\Services\Replay\HostResolver;
use App\Services\Replay\Replayer;
use App\Services\Replay\TargetGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HostResolver::class, DnsHostResolver::class);

        $this->app->bind(TargetGuard::class, fn (Application $app) => new TargetGuard(
            $app->make(HostResolver::class),
            (bool) config('catchhook.replay.allow_private_targets'),
        ));

        $this->app->bind(Replayer::class, fn (Application $app) => new Replayer(
            $app->make(Http::class),
            $app->make(TargetGuard::class),
            (int) config('catchhook.replay.timeout'),
            (int) config('catchhook.replay.max_response_bytes'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        RateLimiter::for('bins', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('replays', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
