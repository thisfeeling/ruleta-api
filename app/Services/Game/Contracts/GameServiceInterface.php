<?php

namespace App\Services\Game\Contracts;

use App\Models\Game;
use App\Models\Player;

interface GameServiceInterface
{
    public function start(Game $game): void;
    public function handlePlayerAction(Game $game, Player $player, array $action): void;
    public function complete(Game $game): array; // Returns elimination/results
    public function getState(Game $game): array;
}
