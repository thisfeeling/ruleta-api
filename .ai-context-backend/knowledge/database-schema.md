# Database Schema - Ruleta Familiar

## Esquema Completo

### `players`
```sql
CREATE TABLE players (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nickname VARCHAR(50) NOT NULL,
  gender ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL,
  color VARCHAR(7) NOT NULL,            -- Hex color #RRGGBB
  number TINYINT UNSIGNED UNIQUE NOT NULL,  -- 1-50
  code CHAR(4) NOT NULL,                -- PIN reconexión
  status ENUM('alive', 'eliminated', 'spectator') DEFAULT 'alive',
  role ENUM('player', 'supervisor') DEFAULT 'player',
  show_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  eliminated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  
  INDEX idx_number (number),
  INDEX idx_code (code),
  INDEX idx_status (status),
  INDEX idx_show (show_id),
  FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE SET NULL
);
```

### `shows`
```sql
CREATE TABLE shows (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  phase ENUM('lobby', 'intro', 'millionaire', 'spell', 'rope', 'roulette', 'winner') DEFAULT 'lobby',
  round TINYINT UNSIGNED DEFAULT 1,
  started_at TIMESTAMP NULL,
  ended_at TIMESTAMP NULL,
  winner_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (winner_id) REFERENCES players(id) ON DELETE SET NULL
);
```

### `system_audios`
```sql
CREATE TABLE system_audios (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  context VARCHAR(50) NOT NULL,
  text TEXT NOT NULL,
  s3_key VARCHAR(255) NOT NULL UNIQUE,
  locale VARCHAR(10) DEFAULT 'es-CO',
  reusable BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_context (context)
);
```

### `number_audios`
```sql
CREATE TABLE number_audios (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  number TINYINT UNSIGNED UNIQUE NOT NULL,
  s3_key VARCHAR(255) NOT NULL UNIQUE,
  locale VARCHAR(10) DEFAULT 'es-CO',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### `spell_audios`
```sql
CREATE TABLE spell_audios (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  player_id BIGINT UNSIGNED NOT NULL,
  word VARCHAR(100) NOT NULL,
  s3_key VARCHAR(255) NOT NULL UNIQUE,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  supervisor_id BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_status (status),
  INDEX idx_player (player_id),
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (supervisor_id) REFERENCES players(id) ON DELETE SET NULL
);
```

### `eliminations`
```sql
CREATE TABLE eliminations (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  player_id BIGINT UNSIGNED NOT NULL,
  show_id BIGINT UNSIGNED NOT NULL,
  game_phase ENUM('millionaire', 'spell', 'rope', 'roulette') NOT NULL,
  reason VARCHAR(255) NULL,
  eliminated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_show (show_id),
  INDEX idx_phase (game_phase),
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE
);
```

### `achievements`
```sql
CREATE TABLE achievements (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `key` VARCHAR(100) UNIQUE NOT NULL,
  name_es VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NOT NULL,
  description_es TEXT NOT NULL,
  description_en TEXT NOT NULL,
  icon VARCHAR(50) NOT NULL,
  points INT NOT NULL DEFAULT 0,
  trigger_type VARCHAR(50) NOT NULL,
  trigger_config JSON,
  is_progressive BOOLEAN DEFAULT FALSE,
  max_progress INT DEFAULT 1,
  rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
  display_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_trigger_type (trigger_type),
  INDEX idx_rarity (rarity)
);
```

### `player_achievements`
```sql
CREATE TABLE player_achievements (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  player_id BIGINT UNSIGNED NOT NULL,
  achievement_id BIGINT UNSIGNED NOT NULL,
  progress INT NOT NULL DEFAULT 0,
  max_progress INT NOT NULL,
  completed_at TIMESTAMP NULL,
  awarded_points INT NOT NULL DEFAULT 0,
  first_progress_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
  UNIQUE KEY unique_player_achievement (player_id, achievement_id),
  INDEX idx_player_completed (player_id, completed_at),
  INDEX idx_achievement_completed (achievement_id, completed_at)
);
```

### `audit_logs`
```sql
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  event_type VARCHAR(100) NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  actor_type ENUM('player', 'supervisor', 'system') NOT NULL,
  target_id BIGINT UNSIGNED NULL,
  target_type VARCHAR(50) NULL,
  context JSON NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_event_type (event_type),
  INDEX idx_actor (actor_type, actor_id),
  INDEX idx_target (target_type, target_id),
  INDEX idx_created_at (created_at),
  INDEX idx_composite_query (event_type, actor_type, created_at)
);
```

### `game_instructions`
```sql
CREATE TABLE game_instructions (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  game_type VARCHAR(50) NOT NULL,
  title_es VARCHAR(255) NOT NULL,
  title_en VARCHAR(255) NOT NULL,
  content_es TEXT NOT NULL,
  content_en TEXT NOT NULL,
  duration_seconds INT NOT NULL DEFAULT 30,
  media_urls JSON NULL,
  display_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_game_type (game_type)
);
```

### `questions` (Millonario)
```sql
CREATE TABLE questions (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  text TEXT NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
  difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
  category VARCHAR(50) NULL,
  used_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_difficulty (difficulty)
);
```

### `word_search_games` (Bonus Game)
```sql
CREATE TABLE word_search_games (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  show_id BIGINT UNSIGNED NOT NULL,
  status ENUM('waiting', 'playing', 'completed', 'cancelled') DEFAULT 'waiting',
  grid_size INT NOT NULL DEFAULT 15,
  total_words INT NOT NULL DEFAULT 12,
  time_limit_seconds INT NOT NULL DEFAULT 300,
  grid_data JSON NOT NULL,
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
  INDEX idx_status (status)
);
```

### `word_search_words`
```sql
CREATE TABLE word_search_words (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NOT NULL,
  word VARCHAR(50) NOT NULL,
  start_row INT NOT NULL,
  start_col INT NOT NULL,
  end_row INT NOT NULL,
  end_col INT NOT NULL,
  direction ENUM('horizontal', 'vertical', 'diagonal_down', 'diagonal_up') NOT NULL,
  found_count INT DEFAULT 0,
  first_found_by BIGINT UNSIGNED NULL,
  first_found_at TIMESTAMP NULL,
  
  FOREIGN KEY (game_id) REFERENCES word_search_games(id) ON DELETE CASCADE,
  FOREIGN KEY (first_found_by) REFERENCES players(id) ON DELETE SET NULL,
  INDEX idx_game (game_id)
);
```

### `player_word_finds`
```sql
CREATE TABLE player_word_finds (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NOT NULL,
  player_id BIGINT UNSIGNED NOT NULL,
  word_id BIGINT UNSIGNED NOT NULL,
  is_valid BOOLEAN NOT NULL DEFAULT TRUE,
  found_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  time_elapsed_seconds INT NULL,
  
  FOREIGN KEY (game_id) REFERENCES word_search_games(id) ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (word_id) REFERENCES word_search_words(id) ON DELETE CASCADE,
  UNIQUE KEY unique_player_word (player_id, game_id, word_id),
  INDEX idx_game_player (game_id, player_id)
);
```

### `flappy_games` (Bonus Game)
```sql
CREATE TABLE flappy_games (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  show_id BIGINT UNSIGNED NOT NULL,
  status ENUM('waiting', 'playing', 'completed', 'cancelled') DEFAULT 'waiting',
  difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
  max_duration_seconds INT NOT NULL DEFAULT 120,
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
  INDEX idx_status (status)
);
```

### `player_flappy_attempts`
```sql
CREATE TABLE player_flappy_attempts (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NOT NULL,
  player_id BIGINT UNSIGNED NOT NULL,
  survival_time_seconds INT NOT NULL,
  pipes_passed INT DEFAULT 0,
  crash_reason ENUM('pipe_collision', 'ground_collision', 'ceiling_collision', 'timeout') NULL,
  crashed_at TIMESTAMP NULL,
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (game_id) REFERENCES flappy_games(id) ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  INDEX idx_game_player (game_id, player_id),
  INDEX idx_survival_time (survival_time_seconds DESC)
);
```

### `player_scores` (Scoreboard Unificado)
```sql
CREATE TABLE player_scores (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  player_id BIGINT UNSIGNED NOT NULL,
  show_id BIGINT UNSIGNED NOT NULL,
  game_type ENUM('millionaire', 'rope', 'spell', 'roulette', 'word_search', 'flappy', 'achievement') NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  raw_score INT NOT NULL,
  normalized_score DECIMAL(7,2) NOT NULL,
  metadata JSON NULL,
  scored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
  INDEX idx_player_show (player_id, show_id),
  INDEX idx_normalized_score (normalized_score DESC),
  INDEX idx_game (game_type, game_id)
);
```

### `chat_messages`
```sql
CREATE TABLE chat_messages (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  player_id BIGINT UNSIGNED NOT NULL,
  show_id BIGINT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_show (show_id),
  INDEX idx_created (created_at),
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE
);
```

---

## Relaciones

```
shows (1) ──< (N) players
shows (1) ──< (N) word_search_games
shows (1) ──< (N) flappy_games

players (1) ──< (N) eliminations
players (1) ──< (N) spell_audios (como player)
players (1) ──< (N) spell_audios (como supervisor)
players (1) ──< (N) chat_messages
players (1) ──< (N) player_achievements
players (1) ──< (N) player_scores
players (1) ──< (N) player_word_finds
players (1) ──< (N) player_flappy_attempts

achievements (1) ──< (N) player_achievements

word_search_games (1) ──< (N) word_search_words
word_search_games (1) ──< (N) player_word_finds
word_search_words (1) ──< (N) player_word_finds

flappy_games (1) ──< (N) player_flappy_attempts

shows (1) ──< (N) chat_messages
shows (1) ──< (N) player_scores
```

---

## Índices Críticos

### Core
- `players.number` - Búsqueda rápida por número
- `players.code` - Reconexión por PIN
- `players.status` - Filtrar vivos/eliminados
- `spell_audios.status` - Audios pendientes de validación
- `chat_messages.show_id` + `created_at` - Mensajes del show actual

### Achievements
- `achievements.trigger_type` - Filtrar por tipo de trigger
- `player_achievements.player_id` + `completed_at` - Progreso del jugador

### Audit
- `audit_logs.event_type` + `actor_type` + `created_at` - Queries supervisor dashboard
- `audit_logs.created_at` - Archivado diario a S3

### Scoreboard
- `player_scores.player_id` + `show_id` - Puntos de un jugador en show
- `player_scores.normalized_score DESC` - Ranking global

### Bonus Games
- `word_search_games.status` - Estado del juego
- `player_word_finds.game_id` + `player_id` - Palabras encontradas
- `flappy_games.status` - Estado del juego
- `player_flappy_attempts.survival_time_seconds DESC` - Leaderboard Flappy

---

## Schemas Completos

Para schemas SQL completos con todas las columnas y relaciones detalladas de los 6 juegos (Millonario, La Cuerda, Deletréalo, Ruleta, Word Search, Flappy), ver:

- **`game-data-models.md`** - Modelos Eloquent, migrations, normalización de puntos
- **`achievement-system.md`** - Sistema de logros completo
- **`audit-system.md`** - Sistema de auditoría dual storage
- **`backend-structure.md`** - Estructura general de carpetas y migraciones

---

**Última actualización**: Diciembre 2025
