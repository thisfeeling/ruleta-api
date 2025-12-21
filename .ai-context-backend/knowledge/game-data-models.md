# Game Data Models - Backend

## Descripción General

Documentación consolidada de los modelos de datos SQL y Eloquent para los **6 juegos** del sistema (4 eliminatorios + 2 bonus). Incluye schemas de migraciones, relaciones entre modelos, casts, accessors/mutators, y lógica de normalización de puntos para el scoreboard unificado.

## Índice de Juegos

1. **Juego del Millonario** - Preguntas y respuestas eliminatorio
2. **La Cuerda** - Competencia grupal eliminatorio
3. **Deletréalo** - Deletreo individual eliminatorio
4. **La Ruleta** - Ruleta final (siempre deja 1 ganador)
5. **¡A buscar!** (Word Search) - Bonus no eliminatorio
6. **No Lo Choques** (Flappy Bird) - Bonus no eliminatorio

---

## 1. Juego del Millonario

### Tablas SQL

#### `millionaire_games`

Representa una instancia del juego Millonario.

```sql
CREATE TABLE millionaire_games (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del show',
    
    -- Estado
    status ENUM('waiting', 'playing', 'completed', 'cancelled') DEFAULT 'waiting',
    
    -- Configuración
    total_questions INT NOT NULL DEFAULT 10,
    time_per_question INT NOT NULL DEFAULT 30 COMMENT 'Segundos',
    
    -- Eliminación
    elimination_percentage DECIMAL(5,2) NOT NULL COMMENT 'Porcentaje a eliminar al final (ej: 33.33)',
    players_to_eliminate INT NULL COMMENT 'Calculado al iniciar',
    
    -- Timestamps
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_show (show_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `millionaire_questions`

Banco de preguntas para Millonario.

```sql
CREATE TABLE millionaire_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Contenido
    question_text TEXT NOT NULL,
    category VARCHAR(100) NULL COMMENT 'Ej: Historia, Ciencia, Cultura Pop',
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    
    -- Respuestas
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    
    -- Metadatos
    times_used INT DEFAULT 0,
    success_rate DECIMAL(5,2) NULL COMMENT 'Porcentaje de aciertos histórico',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_difficulty (difficulty),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `millionaire_question_sets`

Preguntas asignadas a un juego específico.

```sql
CREATE TABLE millionaire_question_sets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    display_order INT NOT NULL COMMENT 'Orden de presentación (1, 2, 3...)',
    
    -- Estadísticas de la pregunta en este juego
    total_answers INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    
    FOREIGN KEY (game_id) REFERENCES millionaire_games(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES millionaire_questions(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_game_question_order (game_id, display_order),
    INDEX idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `player_millionaire_answers`

Respuestas de jugadores.

```sql
CREATE TABLE player_millionaire_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    
    -- Respuesta
    selected_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    is_correct BOOLEAN NOT NULL,
    
    -- Timing
    time_taken_ms INT NOT NULL COMMENT 'Milisegundos que tardó en responder',
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (game_id) REFERENCES millionaire_games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES millionaire_questions(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_player_game_question (player_id, game_id, question_id),
    INDEX idx_player_game (player_id, game_id),
    INDEX idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Modelos Eloquent

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MillionaireGame extends Model
{
    protected $fillable = [
        'show_id', 'status', 'total_questions', 'time_per_question',
        'elimination_percentage', 'players_to_eliminate',
        'started_at', 'completed_at'
    ];
    
    protected $casts = [
        'elimination_percentage' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
    
    // Relationships
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
    
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(MillionaireQuestion::class, 'millionaire_question_sets', 'game_id', 'question_id')
            ->withPivot('display_order', 'total_answers', 'correct_answers')
            ->orderBy('display_order');
    }
    
    public function playerAnswers(): HasMany
    {
        return $this->hasMany(PlayerMillionaireAnswer::class, 'game_id');
    }
    
    // Accessors
    public function getCorrectAnswersCountAttribute(): int
    {
        return $this->playerAnswers()->where('is_correct', true)->count();
    }
    
    // Métodos de negocio
    public function calculatePlayerScore(Player $player): array
    {
        $totalQuestions = $this->total_questions;
        $correctAnswers = $this->playerAnswers()
            ->where('player_id', $player->id)
            ->where('is_correct', true)
            ->count();
        
        $rawScore = $correctAnswers;
        $normalizedScore = ($correctAnswers / $totalQuestions) * 1000;
        
        return [
            'raw_score' => $rawScore,
            'normalized_score' => round($normalizedScore, 2)
        ];
    }
}

class MillionaireQuestion extends Model
{
    protected $fillable = [
        'question_text', 'category', 'difficulty',
        'option_a', 'option_b', 'option_c', 'option_d',
        'correct_answer', 'times_used', 'success_rate'
    ];
    
    protected $casts = [
        'success_rate' => 'decimal:2'
    ];
    
    // Accessor para todas las opciones como array
    public function getOptionsAttribute(): array
    {
        return [
            'A' => $this->option_a,
            'B' => $this->option_b,
            'C' => $this->option_c,
            'D' => $this->option_d,
        ];
    }
}

class PlayerMillionaireAnswer extends Model
{
    protected $fillable = [
        'game_id', 'player_id', 'question_id',
        'selected_answer', 'is_correct', 'time_taken_ms', 'answered_at'
    ];
    
    protected $casts = [
        'is_correct' => 'boolean',
        'answered_at' => 'datetime'
    ];
    
    public function game(): BelongsTo
    {
        return $this->belongsTo(MillionaireGame::class, 'game_id');
    }
    
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
    
    public function question(): BelongsTo
    {
        return $this->belongsTo(MillionaireQuestion::class, 'question_id');
    }
}
```

---

## 2. La Cuerda

### Tablas SQL

#### `rope_games`

Instancia del juego La Cuerda.

```sql
CREATE TABLE rope_games (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NOT NULL,
    
    -- Estado
    status ENUM('grouping', 'voting', 'battling', 'completed', 'cancelled') DEFAULT 'grouping',
    
    -- Configuración
    battle_duration_seconds INT NOT NULL DEFAULT 60,
    
    -- Timestamps
    grouping_ended_at TIMESTAMP NULL,
    voting_ended_at TIMESTAMP NULL,
    battle_ended_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `rope_groups`

Grupos formados en La Cuerda.

```sql
CREATE TABLE rope_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    
    -- Identificación
    group_letter CHAR(1) NOT NULL COMMENT 'A, B, C, D...',
    group_color VARCHAR(20) NULL COMMENT 'red, blue, green, yellow',
    
    -- Resultados
    total_clicks INT DEFAULT 0,
    is_winning_group BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (game_id) REFERENCES rope_games(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_game_group_letter (game_id, group_letter),
    INDEX idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `rope_group_members`

Jugadores asignados a grupos.

```sql
CREATE TABLE rope_group_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    
    -- Estadísticas individuales
    individual_clicks INT DEFAULT 0,
    
    -- Votación
    voted_player_id BIGINT UNSIGNED NULL COMMENT 'A quién votó para eliminar',
    voted_at TIMESTAMP NULL,
    
    FOREIGN KEY (group_id) REFERENCES rope_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (voted_player_id) REFERENCES players(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_group_player (group_id, player_id),
    INDEX idx_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `rope_battles`

Registro de clicks durante la batalla.

```sql
CREATE TABLE rope_battles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    
    -- Click tracking
    click_count INT NOT NULL DEFAULT 1,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (game_id) REFERENCES rope_games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    
    INDEX idx_game_player (game_id, player_id),
    INDEX idx_clicked_at (clicked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Modelos Eloquent

```php
<?php

namespace App\Models;

class RopeGame extends Model
{
    protected $fillable = [
        'show_id', 'status', 'battle_duration_seconds',
        'grouping_ended_at', 'voting_ended_at', 'battle_ended_at', 'completed_at'
    ];
    
    protected $casts = [
        'grouping_ended_at' => 'datetime',
        'voting_ended_at' => 'datetime',
        'battle_ended_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
    
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
    
    public function groups(): HasMany
    {
        return $this->hasMany(RopeGroup::class, 'game_id');
    }
    
    public function battles(): HasMany
    {
        return $this->hasMany(RopeBattle::class, 'game_id');
    }
    
    // Lógica de puntuación: grupos ganadores obtienen puntos
    public function calculatePlayerScore(Player $player): array
    {
        $member = RopeGroupMember::whereHas('group', function($q) {
            $q->where('game_id', $this->id);
        })->where('player_id', $player->id)->first();
        
        if (!$member || !$member->group->is_winning_group) {
            return ['raw_score' => 0, 'normalized_score' => 0];
        }
        
        // Ganadores: 1000 pts, Perdedores: 0 pts
        return ['raw_score' => 1, 'normalized_score' => 1000];
    }
}

class RopeGroup extends Model
{
    protected $fillable = [
        'game_id', 'group_letter', 'group_color', 'total_clicks', 'is_winning_group'
    ];
    
    protected $casts = [
        'is_winning_group' => 'boolean'
    ];
    
    public function game(): BelongsTo
    {
        return $this->belongsTo(RopeGame::class, 'game_id');
    }
    
    public function members(): HasMany
    {
        return $this->hasMany(RopeGroupMember::class, 'group_id');
    }
}

class RopeGroupMember extends Model
{
    protected $fillable = [
        'group_id', 'player_id', 'individual_clicks', 'voted_player_id', 'voted_at'
    ];
    
    protected $casts = [
        'voted_at' => 'datetime'
    ];
    
    public function group(): BelongsTo
    {
        return $this->belongsTo(RopeGroup::class, 'group_id');
    }
    
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
    
    public function votedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'voted_player_id');
    }
}
```

---

## 3. Deletréalo

### Tablas SQL

#### `spell_games`

Instancia de Deletréalo.

```sql
CREATE TABLE spell_games (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NOT NULL,
    
    -- Estado
    status ENUM('waiting', 'playing', 'completed', 'cancelled') DEFAULT 'waiting',
    
    -- Configuración
    max_attempts_per_player INT NOT NULL DEFAULT 1,
    time_per_word_seconds INT NOT NULL DEFAULT 30,
    
    -- Timestamps
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `spell_words`

Banco de palabras para deletrear.

```sql
CREATE TABLE spell_words (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Palabra
    word VARCHAR(100) NOT NULL,
    correct_spelling VARCHAR(100) NOT NULL COMMENT 'Deletreo correcto letra por letra',
    
    -- Dificultad
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    category VARCHAR(50) NULL,
    
    -- Ayudas
    definition TEXT NULL,
    example_sentence TEXT NULL,
    
    -- Estadísticas
    times_used INT DEFAULT 0,
    success_rate DECIMAL(5,2) NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_word (word),
    INDEX idx_difficulty (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `player_spell_attempts`

Intentos de deletreo por jugador.

```sql
CREATE TABLE player_spell_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    word_id BIGINT UNSIGNED NOT NULL,
    
    -- Audio grabado
    audio_url VARCHAR(500) NOT NULL COMMENT 'URL S3 del audio grabado',
    audio_duration_seconds INT NULL,
    
    -- Validación
    is_correct BOOLEAN NULL COMMENT 'NULL=pendiente, true/false=validado por supervisor',
    validated_by BIGINT UNSIGNED NULL COMMENT 'Supervisor que validó',
    validated_at TIMESTAMP NULL,
    validation_notes TEXT NULL,
    
    -- Timing
    time_taken_seconds INT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (game_id) REFERENCES spell_games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (word_id) REFERENCES spell_words(id) ON DELETE CASCADE,
    FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_player_game_word (player_id, game_id, word_id),
    INDEX idx_validation_pending (is_correct),
    INDEX idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Modelos Eloquent

```php
<?php

namespace App\Models;

class SpellGame extends Model
{
    protected $fillable = [
        'show_id', 'status', 'max_attempts_per_player', 'time_per_word_seconds',
        'started_at', 'completed_at'
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
    
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
    
    public function attempts(): HasMany
    {
        return $this->hasMany(PlayerSpellAttempt::class, 'game_id');
    }
    
    // Puntuación: 1 si correcto, 0 si incorrecto
    public function calculatePlayerScore(Player $player): array
    {
        $attempt = $this->attempts()
            ->where('player_id', $player->id)
            ->whereNotNull('is_correct')
            ->first();
        
        if (!$attempt) {
            return ['raw_score' => 0, 'normalized_score' => 0];
        }
        
        $rawScore = $attempt->is_correct ? 1 : 0;
        $normalizedScore = $rawScore * 1000;
        
        return ['raw_score' => $rawScore, 'normalized_score' => $normalizedScore];
    }
}

class SpellWord extends Model
{
    protected $fillable = [
        'word', 'correct_spelling', 'difficulty', 'category',
        'definition', 'example_sentence', 'times_used', 'success_rate'
    ];
    
    protected $casts = [
        'success_rate' => 'decimal:2'
    ];
}

class PlayerSpellAttempt extends Model
{
    protected $fillable = [
        'game_id', 'player_id', 'word_id', 'audio_url', 'audio_duration_seconds',
        'is_correct', 'validated_by', 'validated_at', 'validation_notes',
        'time_taken_seconds', 'recorded_at'
    ];
    
    protected $casts = [
        'is_correct' => 'boolean',
        'validated_at' => 'datetime',
        'recorded_at' => 'datetime'
    ];
    
    public function game(): BelongsTo
    {
        return $this->belongsTo(SpellGame::class, 'game_id');
    }
    
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
    
    public function word(): BelongsTo
    {
        return $this->belongsTo(SpellWord::class, 'word_id');
    }
    
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
```

---

## 4. La Ruleta

### Tablas SQL

#### `roulette_games`

Ruleta final.

```sql
CREATE TABLE roulette_games (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NOT NULL,
    
    -- Estado
    status ENUM('waiting', 'spinning', 'completed', 'cancelled') DEFAULT 'waiting',
    
    -- Configuración
    total_segments INT NOT NULL DEFAULT 12,
    
    -- Winner
    winner_player_id BIGINT UNSIGNED NULL,
    
    -- Timestamps
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_player_id) REFERENCES players(id) ON DELETE SET NULL,
    
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `roulette_segments`

Segmentos de la ruleta.

```sql
CREATE TABLE roulette_segments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    
    -- Segmento
    segment_number INT NOT NULL COMMENT '1 a 12',
    points INT NOT NULL COMMENT 'Puntos del segmento (100-1000)',
    color VARCHAR(20) NULL,
    
    FOREIGN KEY (game_id) REFERENCES roulette_games(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_game_segment (game_id, segment_number),
    INDEX idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `roulette_spins`

Giros de la ruleta por jugador.

```sql
CREATE TABLE roulette_spins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    
    -- Resultado
    landed_segment_id BIGINT UNSIGNED NOT NULL,
    points_won INT NOT NULL,
    
    -- Timing
    spin_order INT NOT NULL COMMENT 'Orden del giro (1, 2, 3...)',
    spun_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (game_id) REFERENCES roulette_games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (landed_segment_id) REFERENCES roulette_segments(id) ON DELETE CASCADE,
    
    INDEX idx_game_player (game_id, player_id),
    INDEX idx_spin_order (game_id, spin_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Modelos Eloquent

```php
<?php

namespace App\Models;

class RouletteGame extends Model
{
    protected $fillable = [
        'show_id', 'status', 'total_segments', 'winner_player_id',
        'started_at', 'completed_at'
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
    
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
    
    public function segments(): HasMany
    {
        return $this->hasMany(RouletteSegment::class, 'game_id');
    }
    
    public function spins(): HasMany
    {
        return $this->hasMany(RouletteSpin::class, 'game_id');
    }
    
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }
    
    // Puntuación: acumulado de giros
    public function calculatePlayerScore(Player $player): array
    {
        $totalPoints = $this->spins()
            ->where('player_id', $player->id)
            ->sum('points_won');
        
        // Max posible: 1000 * n_jugadores en ruleta (ej: 6 jugadores = 6000)
        $maxPossible = 1000 * $this->spins()->distinct('player_id')->count();
        
        $normalizedScore = $maxPossible > 0 ? ($totalPoints / $maxPossible) * 1000 : 0;
        
        return [
            'raw_score' => $totalPoints,
            'normalized_score' => round($normalizedScore, 2)
        ];
    }
}

class RouletteSegment extends Model
{
    protected $fillable = ['game_id', 'segment_number', 'points', 'color'];
    
    public function game(): BelongsTo
    {
        return $this->belongsTo(RouletteGame::class, 'game_id');
    }
}

class RouletteSpin extends Model
{
    protected $fillable = [
        'game_id', 'player_id', 'landed_segment_id', 'points_won', 'spin_order', 'spun_at'
    ];
    
    protected $casts = [
        'spun_at' => 'datetime'
    ];
    
    public function game(): BelongsTo
    {
        return $this->belongsTo(RouletteGame::class, 'game_id');
    }
    
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
    
    public function segment(): BelongsTo
    {
        return $this->belongsTo(RouletteSegment::class, 'landed_segment_id');
    }
}
```

---

## 5. ¡A buscar! (Word Search) - Bonus

### Tablas SQL

#### `word_search_games`

Bonus game de sopa de letras.

```sql
CREATE TABLE word_search_games (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NOT NULL,
    
    -- Estado
    status ENUM('waiting', 'playing', 'completed', 'cancelled') DEFAULT 'waiting',
    
    -- Configuración
    grid_size INT NOT NULL DEFAULT 15 COMMENT 'Grid 15x15',
    total_words INT NOT NULL DEFAULT 12,
    time_limit_seconds INT NOT NULL DEFAULT 300 COMMENT '5 minutos',
    
    -- Grid (JSON)
    grid_data JSON NOT NULL COMMENT 'Array 15x15 de letras',
    
    -- Timestamps
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `word_search_words`

Palabras escondidas en la sopa.

```sql
CREATE TABLE word_search_words (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    
    -- Palabra
    word VARCHAR(50) NOT NULL,
    
    -- Posición en grid
    start_row INT NOT NULL,
    start_col INT NOT NULL,
    end_row INT NOT NULL,
    end_col INT NOT NULL,
    direction ENUM('horizontal', 'vertical', 'diagonal_down', 'diagonal_up') NOT NULL,
    
    -- Estadísticas
    found_count INT DEFAULT 0,
    first_found_by BIGINT UNSIGNED NULL,
    first_found_at TIMESTAMP NULL,
    
    FOREIGN KEY (game_id) REFERENCES word_search_games(id) ON DELETE CASCADE,
    FOREIGN KEY (first_found_by) REFERENCES players(id) ON DELETE SET NULL,
    
    INDEX idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `player_word_finds`

Palabras encontradas por jugadores.

```sql
CREATE TABLE player_word_finds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    word_id BIGINT UNSIGNED NOT NULL,
    
    -- Validación
    is_valid BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Server valida coordenadas',
    
    -- Timing
    found_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    time_elapsed_seconds INT NULL COMMENT 'Segundos desde inicio del juego',
    
    FOREIGN KEY (game_id) REFERENCES word_search_games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (word_id) REFERENCES word_search_words(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_player_word (player_id, game_id, word_id),
    INDEX idx_game_player (game_id, player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Modelos Eloquent

```php
<?php

namespace App\Models;

class WordSearchGame extends Model
{
    protected $fillable = [
        'show_id', 'status', 'grid_size', 'total_words', 'time_limit_seconds',
        'grid_data', 'started_at', 'completed_at'
    ];
    
    protected $casts = [
        'grid_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
    
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
    
    public function words(): HasMany
    {
        return $this->hasMany(WordSearchWord::class, 'game_id');
    }
    
    public function finds(): HasMany
    {
        return $this->hasMany(PlayerWordFind::class, 'game_id');
    }
    
    // Puntuación: (palabras encontradas / 12) * 1000
    public function calculatePlayerScore(Player $player): array
    {
        $wordsFound = $this->finds()
            ->where('player_id', $player->id)
            ->where('is_valid', true)
            ->count();
        
        $normalizedScore = ($wordsFound / $this->total_words) * 1000;
        
        return [
            'raw_score' => $wordsFound,
            'normalized_score' => round($normalizedScore, 2)
        ];
    }
}

class WordSearchWord extends Model
{
    protected $fillable = [
        'game_id', 'word', 'start_row', 'start_col', 'end_row', 'end_col',
        'direction', 'found_count', 'first_found_by', 'first_found_at'
    ];
    
    protected $casts = [
        'first_found_at' => 'datetime'
    ];
    
    public function game(): BelongsTo
    {
        return $this->belongsTo(WordSearchGame::class, 'game_id');
    }
}

class PlayerWordFind extends Model
{
    protected $fillable = [
        'game_id', 'player_id', 'word_id', 'is_valid', 'found_at', 'time_elapsed_seconds'
    ];
    
    protected $casts = [
        'is_valid' => 'boolean',
        'found_at' => 'datetime'
    ];
    
    public function game(): BelongsTo
    {
        return $this->belongsTo(WordSearchGame::class, 'game_id');
    }
    
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
    
    public function word(): BelongsTo
    {
        return $this->belongsTo(WordSearchWord::class, 'word_id');
    }
}
```

---

## 6. No Lo Choques (Flappy Bird) - Bonus

### Tablas SQL

#### `flappy_games`

Bonus game tipo Flappy Bird.

```sql
CREATE TABLE flappy_games (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NOT NULL,
    
    -- Estado
    status ENUM('waiting', 'playing', 'completed', 'cancelled') DEFAULT 'waiting',
    
    -- Configuración
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    max_duration_seconds INT NOT NULL DEFAULT 120 COMMENT 'Máximo 2 minutos',
    
    -- Timestamps
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `player_flappy_attempts`

Intentos de jugadores en Flappy.

```sql
CREATE TABLE player_flappy_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    
    -- Resultado
    survival_time_seconds INT NOT NULL COMMENT 'Segundos sobrevividos',
    pipes_passed INT DEFAULT 0,
    
    -- Crash
    crash_reason ENUM('pipe_collision', 'ground_collision', 'ceiling_collision', 'timeout') NULL,
    crashed_at TIMESTAMP NULL,
    
    -- Timing
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (game_id) REFERENCES flappy_games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    
    INDEX idx_game_player (game_id, player_id),
    INDEX idx_survival_time (survival_time_seconds DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Modelos Eloquent

```php
<?php

namespace App\Models;

class FlappyGame extends Model
{
    protected $fillable = [
        'show_id', 'status', 'difficulty', 'max_duration_seconds',
        'started_at', 'completed_at'
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
    
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
    
    public function attempts(): HasMany
    {
        return $this->hasMany(PlayerFlappyAttempt::class, 'game_id');
    }
    
    // Puntuación: (tiempo sobrevivido / 100) limitado a 1000
    public function calculatePlayerScore(Player $player): array
    {
        $attempt = $this->attempts()
            ->where('player_id', $player->id)
            ->orderBy('survival_time_seconds', 'desc')
            ->first();
        
        if (!$attempt) {
            return ['raw_score' => 0, 'normalized_score' => 0];
        }
        
        $rawScore = $attempt->survival_time_seconds;
        $normalizedScore = min(($rawScore / 100) * 1000, 1000);
        
        return [
            'raw_score' => $rawScore,
            'normalized_score' => round($normalizedScore, 2)
        ];
    }
}

class PlayerFlappyAttempt extends Model
{
    protected $fillable = [
        'game_id', 'player_id', 'survival_time_seconds', 'pipes_passed',
        'crash_reason', 'crashed_at', 'started_at'
    ];
    
    protected $casts = [
        'crashed_at' => 'datetime',
        'started_at' => 'datetime'
    ];
    
    public function game(): BelongsTo
    {
        return $this->belongsTo(FlappyGame::class, 'game_id');
    }
    
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
```

---

## Tabla Unificada: `player_scores`

Todos los juegos registran puntos en esta tabla para el scoreboard global.

```sql
CREATE TABLE player_scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    show_id BIGINT UNSIGNED NOT NULL,
    
    -- Juego
    game_type ENUM('millionaire', 'rope', 'spell', 'roulette', 'word_search', 'flappy', 'achievement') NOT NULL,
    game_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del juego específico',
    
    -- Puntuación
    raw_score INT NOT NULL COMMENT 'Puntuación original del juego',
    normalized_score DECIMAL(7,2) NOT NULL COMMENT 'Puntuación normalizada 0-1000',
    
    -- Metadatos
    metadata JSON NULL COMMENT 'Datos adicionales específicos del juego',
    
    -- Timestamps
    scored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    
    INDEX idx_player_show (player_id, show_id),
    INDEX idx_normalized_score (normalized_score DESC),
    INDEX idx_game (game_type, game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Modelo Eloquent

```php
<?php

namespace App\Models;

class PlayerScore extends Model
{
    protected $fillable = [
        'player_id', 'show_id', 'game_type', 'game_id',
        'raw_score', 'normalized_score', 'metadata', 'scored_at'
    ];
    
    protected $casts = [
        'normalized_score' => 'decimal:2',
        'metadata' => 'array',
        'scored_at' => 'datetime'
    ];
    
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
    
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
    
    // Relación polimórfica al juego
    public function game(): MorphTo
    {
        return $this->morphTo('game', 'game_type', 'game_id');
    }
}
```

---

## Resumen de Normalización de Puntos

| Juego | Raw Score | Normalización | Rango Normalizado |
|-------|-----------|---------------|-------------------|
| **Millonario** | Respuestas correctas (0-10) | `(correct / total) * 1000` | 0-1000 |
| **La Cuerda** | Grupo ganador (0/1) | `isWinner ? 1000 : 0` | 0 o 1000 |
| **Deletréalo** | Correcto (0/1) | `isCorrect ? 1000 : 0` | 0 o 1000 |
| **Ruleta** | Puntos acumulados | `(total / maxPossible) * 1000` | 0-1000 |
| **Word Search** | Palabras encontradas (0-12) | `(found / 12) * 1000` | 0-1000 |
| **Flappy** | Segundos sobrevividos | `min((seconds / 100) * 1000, 1000)` | 0-1000 |
| **Achievement** | Puntos del logro | Ya normalizado | 10-1000 |

---

## Índices de Performance Críticos

Para queries frecuentes del scoreboard:

```sql
-- PlayerScore
INDEX idx_player_show (player_id, show_id);
INDEX idx_normalized_score (normalized_score DESC);

-- MillionaireGame
INDEX idx_status (status);
INDEX idx_player_game (player_id, game_id) ON player_millionaire_answers;

-- RopeGame
INDEX idx_game_player (game_id, player_id) ON rope_battles;

-- WordSearchGame
INDEX idx_game_player (game_id, player_id) ON player_word_finds;

-- FlappyGame
INDEX idx_survival_time (survival_time_seconds DESC) ON player_flappy_attempts;
```

---

**Última actualización**: Diciembre 2025
