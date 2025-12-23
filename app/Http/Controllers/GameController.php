<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Game, Player};
use App\Services\Game\GameEngineService;

class GameController extends Controller
{
    protected GameEngineService $engine;

    public function __construct(GameEngineService $engine)
    {
        $this->engine = $engine;
    }

    public function action(Request $request, Game $game)
    {
        $user = $request->user();

        $player = Player::where('user_id', $user->id)
            ->where('show_id', $game->show_id)
            ->first();

        if (!$player) {
            return response()->json(['message' => 'Player not found for this show'], 404);
        }

        $action = $request->input('action', []);

        $service = $this->engine->getGameService($game->type);
        $service->handlePlayerAction($game, $player, $action);

        return response()->json(['ok' => true]);
    }

    public function state(Request $request, Game $game)
    {
        $service = $this->engine->getGameService($game->type);

        return response()->json(['state' => $service->getState($game)]);
    }

    public function complete(Request $request, Game $game)
    {
        // supervisor middleware should protect this route
        $service = $this->engine->getGameService($game->type);

        $results = $service->complete($game);

        return response()->json($results);
    }
}
