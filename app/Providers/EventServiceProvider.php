<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Show events
        \App\Events\Show\ShowStarted::class => [],
        \App\Events\Show\PhaseChanged::class => [],

        // Game events
        \App\Events\Games\GameStarted::class => [],
        \App\Events\Games\PlayerEliminated::class => [
            \App\Listeners\LogPlayerElimination::class,
        ],

        // Achievement events
        \App\Events\Achievements\AchievementUnlocked::class => [
            \App\Listeners\UpdatePlayerScore::class,
        ],

        // Audio events
        \App\Events\Audio\TrackStarted::class => [
            \App\Listeners\RecordAudioPlay::class,
        ],
    ];

    public function boot(): void
    {
        // You may register any additional boot logic here if needed
    }
}
