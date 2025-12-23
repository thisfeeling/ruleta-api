<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\Achievement\AchievementService;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    protected AchievementService $achievements;

    public function __construct(AchievementService $achievements)
    {
        $this->achievements = $achievements;
    }

    public function me(Request $request)
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if (!$player) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        return response()->json([
            'achievements' => $this->achievements->getPlayerAchievements($player),
            'progress' => $this->achievements->getProgress($player),
        ]);
    }

    public function all()
    {
        return response()->json(\App\Models\Achievement::all());
    }
}
