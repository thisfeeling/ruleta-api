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
players (1) ──< (N) eliminations
players (1) ──< (N) spell_audios (como player)
players (1) ──< (N) spell_audios (como supervisor)
players (1) ──< (N) chat_messages
shows (1) ──< (N) chat_messages
```

---

## Índices Críticos

- `players.number` - Búsqueda rápida por número
- `players.code` - Reconexión por PIN
- `players.status` - Filtrar vivos/eliminados
- `spell_audios.status` - Audios pendientes de validación
- `chat_messages.show_id` + `created_at` - Mensajes del show actual

---

**Ver también**: `backend-structure.md` para las migraciones completas
