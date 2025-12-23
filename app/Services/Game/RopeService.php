<?php

namespace App\Services\Game;

use App\Models\{Game, Player, RopeGroup, RopeVote};
use App\Services\Game\Contracts\GameServiceInterface;

class RopeService implements GameServiceInterface
{
    protected EliminationService $elimination;

    public function __construct(EliminationService $elimination)
    {
        $this->elimination = $elimination;
    }

    public function start(Game $game): void
    {
        $groups = $this->createGroups($game);

        $game->update([
            'state' => [
                'phase' => 'voting',
                'groups' => $groups->pluck('id')->toArray(),
            ],
        ]);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if (($action['type'] ?? '') === 'cast_vote') {
            $this->castVote($game, $player, $action['voted_for_id'] ?? null);
        }

        if (($action['type'] ?? '') === 'click') {
            $this->recordClick($game, $player);
        }
    }

    public function complete(Game $game): array
    {
        $losingGroups = RopeGroup::where('game_id', $game->id)
            ->where('status', 'eliminated')
            ->get();

        $eliminatedPlayers = [];

        foreach ($losingGroups as $group) {
            $players = $group->players;
            $eliminated = $this->elimination->eliminatePlayers($game, $players, 'lost_rope_battle');
            $eliminatedPlayers = array_merge($eliminatedPlayers, $eliminated);
        }

        return [
            'eliminated_count' => count($eliminatedPlayers),
            'eliminated_players' => $eliminatedPlayers,
        ];
    }

    public function getState(Game $game): array
    {
        return $game->state ?? [];
    }

    protected function createGroups(Game $game)
    {
        $activePlayers = Player::where('show_id', $game->show_id)
            ->where('status', 'active')
            ->get();

        $groupCount = (int) ceil($activePlayers->count() / 5);
        $groups = collect();

        $shuffled = $activePlayers->shuffle();

        foreach ($shuffled->chunk($groupCount) as $index => $chunk) {
            $group = RopeGroup::create([
                'game_id' => $game->id,
                'group_name' => 'Grupo ' . chr(65 + $index),
                'members_count' => $chunk->count(),
                'status' => 'voting',
            ]);

            foreach ($chunk as $player) {
                $group->players()->attach($player->id);
            }

            $groups->push($group);
        }

        return $groups;
    }

    protected function castVote(Game $game, Player $voter, ?int $votedForId): void
    {
        if ($votedForId === null) {
            return;
        }

        $group = RopeGroup::whereHas('players', fn($q) => $q->where('player_id', $voter->id))
            ->where('game_id', $game->id)
            ->first();

        if ($group) {
            RopeVote::create([
                'group_id' => $group->id,
                'voter_id' => $voter->id,
                'voted_for_id' => $votedForId,
            ]);
        }
    }

    protected function recordClick(Game $game, Player $player): void
    {
        $group = RopeGroup::whereHas('players', fn($q) => $q->where('player_id', $player->id))
            ->where('game_id', $game->id)
            ->first();

        if ($group) {
            $group->increment('total_clicks');
            $group->players()->updateExistingPivot($player->id, [
                'clicks_contributed' => \DB::raw('clicks_contributed + 1'),
            ]);
        }
    }
}
