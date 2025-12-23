<?php

namespace App\Http\Controllers;

use App\Models\{Show, Player};
use App\Services\Scoreboard\ScoreboardService;
use Illuminate\Http\Request;

class ScoreboardController extends Controller
{
    protected ScoreboardService $scoreboard;

    public function __construct(ScoreboardService $scoreboard)
    {
        $this->scoreboard = $scoreboard;
    }

    public function show(Show $show)
    {
        return response()->json([
            'scoreboard' => $this->scoreboard->getScoreboard($show),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function top(Show $show, Request $request)
    {
        $limit = (int) $request->query('limit', 10);

        return response()->json([
            'top_players' => $this->scoreboard->getTopPlayers($show, $limit),
        ]);
    }

    public function player(Request $request)
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if (!$player) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        return response()->json([
            'rank' => $this->scoreboard->getPlayerRank($player),
            'total_score' => $player->total_score,
            'scores' => $player->scores()->with('game')->get(),
        ]);
    }
}
