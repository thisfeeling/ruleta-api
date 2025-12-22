# 02 - Database Schema

**Status**: [ ] Not Started

## Objetivo

Crear todas las migraciones, seeders y relaciones de base de datos del proyecto.

## Dependencias

- **Anterior**: 01 - Project Setup
- **Sincronización Frontend**: Los modelos TypeScript del frontend deben coincidir con estas tablas

## Estructura de Tablas

### Core Tables
1. `users` - Usuarios (players + supervisors)
2. `shows` - Shows/episodios
3. `players` - Jugadores participantes
4. `player_scores` - Puntuaciones unificadas

### Game Tables
5. `games` - Juegos individuales dentro de un show
6. `millionaire_questions` - Preguntas del Millonario
7. `millionaire_answers` - Respuestas de jugadores
8. `rope_groups` - Grupos en La Cuerda
9. `rope_votes` - Votos de grupos
10. `rope_clicks` - Clicks de batalla
11. `spell_words` - Palabras para deletrear
12. `spell_attempts` - Intentos de deletreo
13. `roulette_spins` - Giros de ruleta
14. `word_search_grids` - Grillas de sopa de letras
15. `word_search_finds` - Palabras encontradas
16. `flappy_attempts` - Intentos de Flappy

### System Tables
17. `achievements` - Definiciones de logros
18. `player_achievements` - Logros desbloqueados
19. `achievement_progress` - Progreso de logros
20. `audio_tracks` - Tracks de audio
21. `audio_plays` - Reproducciones de audio
22. `audit_logs` - Logs de auditoría
23. `game_instructions` - Instrucciones pre-juego

## Implementación

### 2.1 Migration: Users Table

```bash
php artisan make:migration create_users_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['player', 'supervisor'])->default('player');
            $table->string('avatar_url')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['role', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### 2.2 Migration: Shows Table

```bash
php artisan make:migration create_shows_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', [
                'scheduled',
                'lobby',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('scheduled');
            $table->enum('current_phase', [
                'lobby',
                'millionaire_1',
                'spell_1',
                'rope',
                'millionaire_2',
                'spell_2',
                'roulette',
                'word_search',  // bonus
                'flappy',       // bonus
                'results'
            ])->nullable();
            $table->integer('max_players')->default(50);
            $table->integer('current_player_count')->default(0);
            $table->foreignId('winner_id')->nullable()->constrained('users');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('settings')->nullable(); // Game-specific settings
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'scheduled_at']);
            $table->index('current_phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shows');
    }
};
```

### 2.3 Migration: Players Table

```bash
php artisan make:migration create_players_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('player_number')->comment('Display number 1-50');
            $table->string('pin', 4)->unique()->comment('Reconnection PIN');
            $table->enum('status', [
                'active',
                'eliminated',
                'disconnected',
                'winner'
            ])->default('active');
            $table->integer('elimination_order')->nullable();
            $table->string('eliminated_by_game')->nullable(); // millionaire, rope, etc
            $table->integer('total_score')->default(0);
            $table->json('stats')->nullable(); // Custom stats per player
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['show_id', 'player_number']);
            $table->unique(['show_id', 'user_id']);
            $table->index(['show_id', 'status']);
            $table->index('pin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
```

### 2.4 Migration: Player Scores Table (Unified Scoreboard)

```bash
php artisan make:migration create_player_scores_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->enum('game_type', [
                'millionaire',
                'rope',
                'spell',
                'roulette',
                'word_search',
                'flappy'
            ]);
            $table->integer('raw_score')->comment('Score specific to game type');
            $table->integer('normalized_score')->comment('0-1000 normalized');
            $table->json('metadata')->nullable(); // Game-specific data
            $table->timestamps();
            
            $table->index(['player_id', 'game_type']);
            $table->index(['game_id', 'normalized_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_scores');
    }
};
```

### 2.5 Migration: Games Table

```bash
php artisan make:migration create_games_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->onDelete('cascade');
            $table->enum('type', [
                'millionaire',
                'rope',
                'spell',
                'roulette',
                'word_search',
                'flappy'
            ]);
            $table->integer('round_number')->comment('1 for first occurrence, 2 for second');
            $table->enum('status', [
                'pending',
                'instructions',
                'active',
                'completed',
                'cancelled'
            ])->default('pending');
            $table->boolean('is_bonus')->default(false);
            $table->integer('players_at_start')->nullable();
            $table->integer('players_eliminated')->default(0);
            $table->json('config')->nullable(); // Game-specific configuration
            $table->json('state')->nullable(); // Current game state
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['show_id', 'type', 'round_number']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
```

### 2.6 Migration: Millionaire Questions

```bash
php artisan make:migration create_millionaire_questions_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('millionaire_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->integer('question_number');
            $table->text('question_text_es');
            $table->text('question_text_en');
            $table->string('option_a_es');
            $table->string('option_a_en');
            $table->string('option_b_es');
            $table->string('option_b_en');
            $table->string('option_c_es');
            $table->string('option_c_en');
            $table->string('option_d_es');
            $table->string('option_d_en');
            $table->enum('correct_answer', ['A', 'B', 'C', 'D']);
            $table->integer('time_limit_seconds')->default(15);
            $table->string('audio_question_url')->nullable();
            $table->timestamps();
            
            $table->index(['game_id', 'question_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('millionaire_questions');
    }
};
```

### 2.7 Migration: Millionaire Answers

```bash
php artisan make:migration create_millionaire_answers_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('millionaire_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('millionaire_questions')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->enum('selected_answer', ['A', 'B', 'C', 'D'])->nullable();
            $table->boolean('is_correct')->nullable();
            $table->integer('time_taken_ms')->nullable();
            $table->boolean('timed_out')->default(false);
            $table->timestamps();
            
            $table->unique(['question_id', 'player_id']);
            $table->index(['player_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('millionaire_answers');
    }
};
```

### 2.8 Migration: Rope Groups

```bash
php artisan make:migration create_rope_groups_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rope_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->string('group_name'); // "Grupo A", "Grupo B"
            $table->integer('total_clicks')->default(0);
            $table->integer('members_count');
            $table->enum('status', ['voting', 'battle', 'eliminated', 'safe'])->default('voting');
            $table->foreignId('voted_out_player_id')->nullable()->constrained('players');
            $table->timestamps();
            
            $table->index(['game_id', 'status']);
        });
        
        // Pivot table for players in groups
        Schema::create('group_player', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('rope_groups')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->integer('clicks_contributed')->default(0);
            $table->timestamps();
            
            $table->primary(['group_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_player');
        Schema::dropIfExists('rope_groups');
    }
};
```

### 2.9 Migration: Rope Votes

```bash
php artisan make:migration create_rope_votes_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rope_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('rope_groups')->onDelete('cascade');
            $table->foreignId('voter_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('voted_for_id')->constrained('players')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['group_id', 'voter_id']);
            $table->index(['group_id', 'voted_for_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rope_votes');
    }
};
```

### 2.10 Migration: Spell Words

```bash
php artisan make:migration create_spell_words_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spell_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->string('word');
            $table->enum('difficulty', ['easy', 'medium', 'hard']);
            $table->integer('time_limit_seconds');
            $table->string('audio_recording_url')->nullable();
            $table->enum('status', ['pending', 'recording', 'reviewing', 'completed'])->default('pending');
            $table->timestamps();
            
            $table->index(['game_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spell_words');
    }
};
```

### 2.11 Migration: Spell Attempts

```bash
php artisan make:migration create_spell_attempts_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spell_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spell_word_id')->constrained('spell_words')->onDelete('cascade');
            $table->string('submitted_spelling');
            $table->boolean('is_correct');
            $table->foreignId('reviewed_by')->nullable()->constrained('users'); // Supervisor
            $table->text('review_notes')->nullable();
            $table->integer('time_taken_ms');
            $table->timestamps();
            
            $table->index(['spell_word_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spell_attempts');
    }
};
```

### 2.12 Migration: Roulette Spins

```bash
php artisan make:migration create_roulette_spins_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roulette_spins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->integer('round_number');
            $table->integer('points_won');
            $table->boolean('eliminated')->default(false);
            $table->integer('cumulative_score');
            $table->float('spin_duration_seconds');
            $table->timestamps();
            
            $table->index(['game_id', 'player_id']);
            $table->index(['game_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roulette_spins');
    }
};
```

### 2.13 Migration: Word Search Grids

```bash
php artisan make:migration create_word_search_grids_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_search_grids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->json('grid'); // 15x15 matrix
            $table->json('words'); // Array of {word, startRow, startCol, direction}
            $table->integer('time_limit_seconds')->default(180);
            $table->timestamps();
            
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_search_grids');
    }
};
```

### 2.14 Migration: Word Search Finds

```bash
php artisan make:migration create_word_search_finds_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_search_finds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grid_id')->constrained('word_search_grids')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->string('word');
            $table->integer('find_order'); // 1st, 2nd, 3rd...
            $table->integer('time_elapsed_ms');
            $table->timestamps();
            
            $table->unique(['grid_id', 'player_id', 'word']);
            $table->index(['grid_id', 'find_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_search_finds');
    }
};
```

### 2.15 Migration: Flappy Attempts

```bash
php artisan make:migration create_flappy_attempts_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flappy_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->integer('survival_time_ms');
            $table->integer('pipes_passed')->default(0);
            $table->integer('score');
            $table->timestamps();
            
            $table->index(['game_id', 'survival_time_ms']);
            $table->index(['player_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flappy_attempts');
    }
};
```

### 2.16 Migration: Achievements

```bash
php artisan make:migration create_achievements_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'millionaire_perfect', 'first_blood', etc
            $table->string('name_es');
            $table->string('name_en');
            $table->text('description_es');
            $table->text('description_en');
            $table->string('icon')->nullable();
            $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common');
            $table->integer('points')->default(0);
            $table->boolean('is_secret')->default(false);
            $table->json('unlock_criteria')->nullable(); // Flexible criteria
            $table->timestamps();
            
            $table->index('key');
            $table->index('rarity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
```

### 2.17 Migration: Player Achievements

```bash
php artisan make:migration create_player_achievements_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained('achievements')->onDelete('cascade');
            $table->foreignId('show_id')->constrained('shows')->onDelete('cascade');
            $table->dateTime('unlocked_at');
            $table->json('context')->nullable(); // What triggered it
            $table->timestamps();
            
            $table->unique(['player_id', 'achievement_id', 'show_id']);
            $table->index(['player_id', 'unlocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_achievements');
    }
};
```

### 2.18 Migration: Achievement Progress

```bash
php artisan make:migration create_achievement_progress_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievement_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained('achievements')->onDelete('cascade');
            $table->integer('current_progress')->default(0);
            $table->integer('required_progress');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['player_id', 'achievement_id']);
            $table->index(['player_id', 'current_progress']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_progress');
    }
};
```

### 2.19 Migration: Audio Tracks

```bash
php artisan make:migration create_audio_tracks_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'narrator.welcome', 'music.lobby', 'sfx.click'
            $table->enum('channel', ['music', 'sfx', 'voice']);
            $table->string('s3_path');
            $table->string('s3_url');
            $table->integer('duration_ms')->nullable();
            $table->float('default_volume')->default(1.0);
            $table->json('metadata')->nullable(); // Tags, categories, etc
            $table->boolean('is_preloaded')->default(false);
            $table->timestamps();
            
            $table->index('key');
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_tracks');
    }
};
```

### 2.20 Migration: Audio Plays

```bash
php artisan make:migration create_audio_plays_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained('audio_tracks')->onDelete('cascade');
            $table->foreignId('show_id')->nullable()->constrained('shows')->onDelete('cascade');
            $table->foreignId('played_by')->nullable()->constrained('users');
            $table->dateTime('played_at');
            $table->string('context')->nullable(); // 'game_start', 'question_reveal', etc
            $table->timestamps();
            
            $table->index(['track_id', 'played_at']);
            $table->index(['show_id', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_plays');
    }
};
```

### 2.21 Migration: Audit Logs (Partitioned)

```bash
php artisan make:migration create_audit_logs_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->nullable()->constrained('shows')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('event_type', [
                'game_start',
                'game_end',
                'player_eliminated',
                'answer_submitted',
                'vote_cast',
                'word_found',
                'achievement_unlocked',
                'supervisor_action',
                'system_event'
            ]);
            $table->string('entity_type')->nullable(); // 'Player', 'Game', etc
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('payload'); // Full event data
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('s3_backup_key')->nullable(); // Key in S3 permanent storage
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['show_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
        
        // Create monthly partitions for last 3 months
        $this->createPartitions();
    }
    
    private function createPartitions(): void
    {
        $months = collect(range(0, 2))->map(fn($i) => now()->subMonths($i));
        
        foreach ($months as $month) {
            $partitionName = 'p' . $month->format('Y_m');
            $nextMonth = $month->copy()->addMonth();
            
            DB::statement("
                ALTER TABLE audit_logs 
                PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) (
                    PARTITION {$partitionName} VALUES LESS THAN (UNIX_TIMESTAMP('{$nextMonth->toDateString()}'))
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

### 2.22 Migration: Game Instructions

```bash
php artisan make:migration create_game_instructions_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_instructions', function (Blueprint $table) {
            $table->id();
            $table->enum('game_type', [
                'millionaire',
                'rope',
                'spell',
                'roulette',
                'word_search',
                'flappy'
            ]);
            $table->text('content_es');
            $table->text('content_en');
            $table->string('audio_es_url')->nullable();
            $table->string('audio_en_url')->nullable();
            $table->integer('estimated_duration_seconds');
            $table->timestamps();
            
            $table->unique('game_type');
        });
        
        // Tracking table
        Schema::create('instruction_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('instruction_id')->constrained('game_instructions')->onDelete('cascade');
            $table->boolean('completed')->default(false);
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            
            $table->unique(['game_id', 'player_id']);
            $table->index(['instruction_id', 'completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruction_reads');
        Schema::dropIfExists('game_instructions');
    }
};
```

## Seeders

### 2.23 Achievement Seeder

```bash
php artisan make:seeder AchievementSeeder
```

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Achievement;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            // Millionaire Achievements
            [
                'key' => 'millionaire_perfect',
                'name_es' => '¡Cerebro Total!',
                'name_en' => 'Total Brain!',
                'description_es' => 'Responde todas las preguntas correctamente',
                'description_en' => 'Answer all questions correctly',
                'rarity' => 'legendary',
                'points' => 500,
            ],
            [
                'key' => 'millionaire_speed_demon',
                'name_es' => 'Relámpago Mental',
                'name_en' => 'Speed Demon',
                'description_es' => 'Responde 5 preguntas en menos de 3 segundos cada una',
                'description_en' => 'Answer 5 questions in less than 3 seconds each',
                'rarity' => 'epic',
                'points' => 300,
            ],
            
            // Rope Achievements
            [
                'key' => 'rope_click_master',
                'name_es' => 'Dedo Rápido',
                'name_en' => 'Click Master',
                'description_es' => 'Haz más de 200 clicks en la batalla',
                'description_en' => 'Make more than 200 clicks in battle',
                'rarity' => 'rare',
                'points' => 200,
            ],
            
            // Spell Achievements
            [
                'key' => 'spell_perfect',
                'name_es' => 'Ortografía Perfecta',
                'name_en' => 'Perfect Spelling',
                'description_es' => 'Deletrea tu palabra correctamente al primer intento',
                'description_en' => 'Spell your word correctly on first try',
                'rarity' => 'common',
                'points' => 100,
            ],
            
            // Roulette Achievements
            [
                'key' => 'roulette_lucky',
                'name_es' => 'Súper Suertudo',
                'name_en' => 'Super Lucky',
                'description_es' => 'Gana más de 800 puntos en la ruleta',
                'description_en' => 'Win more than 800 points in roulette',
                'rarity' => 'epic',
                'points' => 400,
            ],
            
            // Bonus Games
            [
                'key' => 'word_search_speed',
                'name_es' => 'Vista de Águila',
                'name_en' => 'Eagle Eye',
                'description_es' => 'Encuentra 5 palabras en menos de 30 segundos',
                'description_en' => 'Find 5 words in less than 30 seconds',
                'rarity' => 'rare',
                'points' => 250,
            ],
            [
                'key' => 'flappy_survivor',
                'name_es' => 'Pájaro Invencible',
                'name_en' => 'Invincible Bird',
                'description_es' => 'Sobrevive más de 60 segundos',
                'description_en' => 'Survive more than 60 seconds',
                'rarity' => 'epic',
                'points' => 350,
            ],
            
            // General Achievements
            [
                'key' => 'first_blood',
                'name_es' => 'Primera Sangre',
                'name_en' => 'First Blood',
                'description_es' => 'Sé el primer jugador eliminado',
                'description_en' => 'Be the first player eliminated',
                'rarity' => 'common',
                'points' => 50,
                'is_secret' => true,
            ],
            [
                'key' => 'show_winner',
                'name_es' => '¡Campeón!',
                'name_en' => 'Champion!',
                'description_es' => 'Gana el show completo',
                'description_en' => 'Win the entire show',
                'rarity' => 'legendary',
                'points' => 1000,
            ],
        ];
        
        foreach ($achievements as $achievement) {
            Achievement::create($achievement);
        }
    }
}
```

### 2.24 Database Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AchievementSeeder::class,
            // GameInstructionSeeder::class, // Create this next
            // AudioTrackSeeder::class, // Create in next step
        ]);
    }
}
```

## Verificación

```bash
# Run all migrations
php artisan migrate

# Seed database
php artisan db:seed

# Verify tables
php artisan tinker
>>> DB::select('SHOW TABLES');

# Check relationships
>>> \App\Models\Show::first()->players;
>>> \App\Models\Player::first()->scores;
```

## Sincronización con Frontend

El frontend debe crear interfaces TypeScript que coincidan con estas tablas. Ver:
- `game-data-models.md` en frontend para interfaces
- Todas las enums deben coincidir exactamente
- Los campos `*_es` y `*_en` se mapean a i18n en frontend

## Próximos Pasos

→ **03 - Models & Relationships**: Crear modelos Eloquent
