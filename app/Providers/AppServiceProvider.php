<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Storage\StorageService;
use App\Services\TTS\TTSService;
use App\Services\Audio\AudioService;
use App\Services\Player\PINGeneratorService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StorageService::class);
        $this->app->singleton(TTSService::class);
        $this->app->singleton(AudioService::class);
        $this->app->singleton(PINGeneratorService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
