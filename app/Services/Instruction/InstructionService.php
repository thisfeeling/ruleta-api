<?php

namespace App\Services\Instruction;

use App\Models\{Game, Player, GameInstruction, InstructionRead};
use App\Events\Instructions\{InstructionsRequired, InstructionsCompleted};

class InstructionService
{
    public function getInstructions(string $gameType): ?GameInstruction
    {
        return GameInstruction::where('game_type', $gameType)->first();
    }

    public function requireInstructions(Game $game): void
    {
        $instruction = $this->getInstructions($game->type);

        if (!$instruction) {
            return;
        }

        $game->update(['status' => 'instructions']);

        event(new InstructionsRequired($game, $instruction));

        // Track reads for all active players
        $activePlayers = Player::where('show_id', $game->show_id)
            ->where('status', 'active')
            ->get();

        foreach ($activePlayers as $player) {
            InstructionRead::updateOrCreate(
                ['game_id' => $game->id, 'player_id' => $player->id],
                [
                    'instruction_id' => $instruction->id,
                    'started_at' => now(),
                    'completed' => false,
                ]
            );
        }
    }

    public function markAsRead(Game $game, Player $player): void
    {
        $read = InstructionRead::where('game_id', $game->id)
            ->where('player_id', $player->id)
            ->first();

        if ($read && !$read->completed) {
            $read->update([
                'completed' => true,
                'completed_at' => now(),
            ]);
        }

        // Check if all players have read
        $this->checkAllCompleted($game);
    }

    public function checkAllCompleted(Game $game): bool
    {
        $totalReads = InstructionRead::where('game_id', $game->id)->count();
        $completedReads = InstructionRead::where('game_id', $game->id)
            ->where('completed', true)
            ->count();

        if ($totalReads > 0 && $totalReads === $completedReads) {
            event(new InstructionsCompleted($game));
            return true;
        }

        return false;
    }

    public function getReadStatus(Game $game): array
    {
        $reads = InstructionRead::where('game_id', $game->id)
            ->with('player')
            ->get();

        return [
            'total' => $reads->count(),
            'completed' => $reads->where('completed', true)->count(),
            'pending' => $reads->where('completed', false)->count(),
            'reads' => $reads,
        ];
    }
}
