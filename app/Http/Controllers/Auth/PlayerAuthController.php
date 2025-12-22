<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{Player, Show, User};
use App\Services\Player\PINGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlayerAuthController extends Controller
{
    public function __construct(
        protected PINGeneratorService $pinGenerator
    ) {}

    public function join(Request $request)
    {
        $request->validate([
            'show_id' => 'required|exists:shows,id',
            'name' => 'required|string|max:255',
        ]);

        $show = Show::findOrFail($request->show_id);

        if (method_exists($show, 'canAcceptPlayers') && !$show->canAcceptPlayers()) {
            return response()->json(['error' => 'Show is full or not accepting players'], 403);
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => 'player-' . uniqid() . '@temp.local',
            'password' => Hash::make(Str::random(32)),
            'role' => 'player',
        ]);

        // Create player
        $player = Player::create([
            'show_id' => $show->id,
            'user_id' => $user->id,
            'player_number' => ($show->current_player_count ?? 0) + 1,
            'pin' => $this->pinGenerator->generate(),
            'status' => 'active',
        ]);

        $show->increment('current_player_count');

        $newToken = $user->createToken('player-token');

        if (config('auth.token_expiration_minutes')) {
            $newToken->accessToken->expires_at = now()->addMinutes(config('auth.token_expiration_minutes'));
            $newToken->accessToken->save();
        }

        return response()->json([
            'user' => $user,
            'player' => $player,
            'token' => $newToken->plainTextToken,
            'pin' => $player->pin,
        ]);
    }

    public function reconnect(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|size:4',
        ]);

        $player = $this->pinGenerator->findPlayer($request->pin);

        if (!$player) {
            return response()->json(['error' => 'PIN inválido o jugador no activo'], 404);
        }

        $player->update(['status' => 'active']);

        $newToken = $player->user->createToken('reconnect-token');

        if (config('auth.token_expiration_minutes')) {
            $newToken->accessToken->expires_at = now()->addMinutes(config('auth.token_expiration_minutes'));
            $newToken->accessToken->save();
        }

        return response()->json([
            'user' => $player->user,
            'player' => $player,
            'token' => $newToken->plainTextToken,
        ]);
    }
}
