# 16 - Instructions System

**Status**: [x] Completed

## Objetivo

Sistema de instrucciones pre-juego con tracking de lectura y audio TTS.

## Dependencias

- **Anterior**: 15 - Audit System

## Implementación

### 16.1 Instruction Service

```php
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
            InstructionRead::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'instruction_id' => $instruction->id,
                'started_at' => now(),
            ]);
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
```

### 16.2 Instruction Seeder

```bash
php artisan make:seeder GameInstructionSeeder
```

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameInstruction;

class GameInstructionSeeder extends Seeder
{
    public function run(): void
    {
        $instructions = [
            [
                'game_type' => 'millionaire',
                'content_es' => '¡Bienvenidos al Juego del Millonario! Responderás preguntas de cultura general con 4 opciones. Tienes 15 segundos por pregunta. Los jugadores con menos respuestas correctas serán eliminados.',
                'content_en' => 'Welcome to the Millionaire Game! You will answer general knowledge questions with 4 options. You have 15 seconds per question. Players with fewer correct answers will be eliminated.',
                'estimated_duration_seconds' => 30,
            ],
            [
                'game_type' => 'rope',
                'content_es' => 'En La Cuerda, serán divididos en grupos. Primero, votarán para elegir a un miembro. Luego, los grupos competirán haciendo click. El grupo perdedor será eliminado.',
                'content_en' => 'In The Rope, you will be divided into groups. First, you will vote to choose a member. Then, groups will compete by clicking. The losing group will be eliminated.',
                'estimated_duration_seconds' => 40,
            ],
            [
                'game_type' => 'spell',
                'content_es' => 'Deletréalo: Se te asignará una palabra. Deberás deletrearla usando tu voz. Un supervisor validará tu respuesta. Si te equivocas, serás eliminado.',
                'content_en' => 'Spell It: You will be assigned a word. You must spell it using your voice. A supervisor will validate your answer. If you are wrong, you will be eliminated.',
                'estimated_duration_seconds' => 35,
            ],
            [
                'game_type' => 'roulette',
                'content_es' => 'La Ruleta Final: Cada jugador girará la ruleta para acumular puntos. El jugador con menos puntos al final será eliminado. ¡Solo quedará UN ganador!',
                'content_en' => 'The Final Roulette: Each player will spin the roulette to accumulate points. The player with the fewest points at the end will be eliminated. Only ONE winner will remain!',
                'estimated_duration_seconds' => 30,
            ],
            [
                'game_type' => 'word_search',
                'content_es' => '¡A Buscar! Este es un juego bonus. Encuentra todas las palabras en la sopa de letras lo más rápido posible. NO hay eliminación, solo puntos para el scoreboard.',
                'content_en' => 'Word Search! This is a bonus game. Find all words in the word search as fast as possible. NO elimination, only points for the scoreboard.',
                'estimated_duration_seconds' => 25,
            ],
            [
                'game_type' => 'flappy',
                'content_es' => 'No Lo Choques: Juego bonus tipo Flappy Bird. Sobrevive el mayor tiempo posible. NO hay eliminación, solo puntos por tiempo de supervivencia.',
                'content_en' => 'Don\'t Crash It: Flappy Bird-style bonus game. Survive as long as possible. NO elimination, only points for survival time.',
                'estimated_duration_seconds' => 20,
            ],
        ];
        
        foreach ($instructions as $instruction) {
            GameInstruction::create($instruction);
        }
    }
}
```

### 16.3 Instructions Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\{Game, Player};
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
```

## Estado de Implementación

**Actualización (Backend):** Se añadió la implementación inicial del sistema de instrucciones:

- `InstructionRead` model actualizado para reflejar la migración (`game_id`, `player_id`, `instruction_id`, `completed`, `started_at`, `completed_at`) y relaciones.
- `InstructionService` con métodos `requireInstructions`, `markAsRead`, `checkAllCompleted`, `getReadStatus`.
- Eventos: `InstructionsRequired`, `InstructionsCompleted` (broadcasting).
- `InstructionController` con endpoints `markAsRead`, `getStatus`, `forceComplete`.
- `GameInstructionSeeder` y registro en `DatabaseSeeder`.
- Tests unitarios para `InstructionService` y pruebas funcionales básicas para el controller.

**Pendiente:** Ejecutar test suite y CI (ver todo: tests/Unit + tests/Feature) para validar comportamiento.

## Próximos Pasos

→ **17 - Scoreboard System**: Normalización y ranking
→ **18 - Testing**: Test suite
