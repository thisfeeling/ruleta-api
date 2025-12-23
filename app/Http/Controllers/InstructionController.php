<?php

namespace App\Http\Controllers;

use App\Models\{Game, Player, InstructionRead};
use App\Services\Instruction\InstructionService;
use Illuminate\Http\Request;

class InstructionController extends Controller
{
    protected InstructionService $instructions;

    public function __construct(InstructionService $instructions)
    {
        $this->instructions = $instructions;
    }

    public function markAsRead(Request $request, Game $game)
    {
        $player = Player::where('user_id', $request->user()->id)
            ->where('show_id', $game->show_id)
            ->first();

        if (!$player) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $this->instructions->markAsRead($game, $player);

        return response()->json(['message' => 'Instructions marked as read']);
    }

    public function getStatus(Game $game)
    {
        return response()->json($this->instructions->getReadStatus($game));
    }

    public function forceComplete(Request $request, Game $game)
    {
        // Supervisor only
        if (!$request->user()->isSupervisor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        InstructionRead::where('game_id', $game->id)
            ->where('completed', false)
            ->update([
                'completed' => true,
                'completed_at' => now(),
            ]);

        $this->instructions->checkAllCompleted($game);

        return response()->json(['message' => 'All instructions marked as completed']);
    }
}
