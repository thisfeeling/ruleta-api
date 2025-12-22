<?php

namespace App\Services\Game;

use App\Models\{Show, Game};
use App\Events\Games\{GameStarted, GameEnded};
use App\Services\Game\Contracts\GameServiceInterface;

class GameEngineService
{
    protected array $gameServices = [];

    public function registerGameService(string $gameType, GameServiceInterface $service): void
    {
        $this->gameServices[$gameType] = $service;
    }

    public function getGameService(string $gameType): GameServiceInterface
    {
        if (!isset($this->gameServices[$gameType])) {
            throw new \Exception("Game service not found for type: {$gameType}");
        }

        return $this->gameServices[$gameType];
    }

    public function startGame(Game $game): void
    {
        $game->start();

        $service = $this->getGameService($game->type);
        $service->start($game);

        event(new GameStarted($game));
    }

    public function completeGame(Game $game): void
    {
        $service = $this->getGameService($game->type);
        $results = $service->complete($game);

        $game->complete();

        event(new GameEnded($game, $results));
    }
}
