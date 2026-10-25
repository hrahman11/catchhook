<?php

namespace App\Providers;

use App\Models\Bin;
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
            config()->boolean('catchhook.replay.allow_private_targets'),
        ));

        $this->app->bind(Replayer::class, fn (Application $app) => new Replayer(
            $app->make(Http::class),
            $app->make(TargetGuard::class),
            config()->integer('catchhook.replay.timeout'),
            config()->integer('catchhook.replay.max_response_bytes'),
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

        // Runs before route model binding, so the bin parameter is still the raw ID.
        RateLimiter::for('captures', function (Request $request) {
            $bin = $request->route('bin');
            $binId = $bin instanceof Bin ? $bin->id : (string) $bin;

            return [
                Limit::perMinute(config()->integer('catchhook.limits.captures_per_minute_per_bin'))->by("bin:{$binId}"),
                Limit::perMinute(config()->integer('catchhook.limits.captures_per_minute_per_ip'))->by("ip:{$request->ip()}"),
            ];
        });
    }
}
