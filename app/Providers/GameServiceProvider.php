<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Game\{
    GameEngineService,
    ShowStateMachineService,
    EliminationService
};

class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameEngineService::class);
        $this->app->singleton(ShowStateMachineService::class);
        $this->app->singleton(EliminationService::class);
    }

    public function boot(): void
    {
        $engine = $this->app->make(GameEngineService::class);

        // Register game implementations (created in next steps)
        // $engine->registerGameService('millionaire', $this->app->make(MillionaireService::class));
        // $engine->registerGameService('rope', $this->app->make(RopeService::class));
        // etc...
    }
}
