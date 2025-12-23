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

        // Register game implementations
        $engine->registerGameService('millionaire', $this->app->make(\App\Services\Game\MillionaireService::class));
        $engine->registerGameService('rope', $this->app->make(\App\Services\Game\RopeService::class));
        $engine->registerGameService('spell', $this->app->make(\App\Services\Game\SpellService::class));
        $engine->registerGameService('roulette', $this->app->make(\App\Services\Game\RouletteService::class));
        $engine->registerGameService('word_search', $this->app->make(\App\Services\Game\WordSearchService::class));
        $engine->registerGameService('flappy', $this->app->make(\App\Services\Game\FlappyService::class));
    }
}
