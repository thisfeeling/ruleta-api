# 18 - Testing Strategy

**Status**: [ ] Not Started

## Objetivo

Crear test suite completo: unit tests, feature tests, integration tests con Pest.

## Dependencias

- **Anterior**: 17 - Scoreboard System

## Setup

```bash
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel

php artisan pest:install
```

## Estructura de Tests

```
tests/
├── Feature/           # Feature/integration tests
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   ├── PlayerJoinTest.php
│   │   └── ReconnectTest.php
│   ├── Game/
│   │   ├── MillionaireTest.php
│   │   ├── RopeTest.php
│   │   ├── SpellTest.php
│   │   ├── RouletteTest.php
│   │   ├── WordSearchTest.php
│   │   └── FlappyTest.php
│   ├── Achievement/
│   │   └── AchievementUnlockTest.php
│   ├── Scoreboard/
│   │   └── ScoreboardTest.php
│   └── WebSocket/
│       └── BroadcastingTest.php
├── Unit/              # Unit tests
│   ├── Services/
│   │   ├── AudioServiceTest.php
│   │   ├── StorageServiceTest.php
│   │   ├── EliminationServiceTest.php
│   │   └── ScoreboardServiceTest.php
│   ├── Models/
│   │   ├── PlayerTest.php
│   │   └── GameTest.php
│   └── Helpers/
│       └── PINGeneratorTest.php
└── Pest.php           # Pest configuration
```

## Ejemplos de Tests

### Feature Test: Player Join

```php
<?php

use App\Models\{Show, Player};

test('player can join a show', function () {
    $show = Show::factory()->create([
        'status' => 'lobby',
        'max_players' => 50,
        'current_player_count' => 0,
    ]);
    
    $response = $this->postJson('/api/auth/player/join', [
        'show_id' => $show->id,
        'name' => 'Test Player',
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'user',
            'player',
            'token',
            'pin',
        ]);
    
    expect(Player::count())->toBe(1);
    expect($show->fresh()->current_player_count)->toBe(1);
});

test('player cannot join full show', function () {
    $show = Show::factory()->create([
        'status' => 'lobby',
        'max_players' => 50,
        'current_player_count' => 50,
    ]);
    
    $response = $this->postJson('/api/auth/player/join', [
        'show_id' => $show->id,
        'name' => 'Test Player',
    ]);
    
    $response->assertStatus(403);
});
```

### Feature Test: Millionaire Game

```php
<?php

use App\Models\{Show, Game, Player, MillionaireQuestion, MillionaireAnswer};
use App\Services\Game\MillionaireService;

test('millionaire game eliminates worst performers', function () {
    $show = Show::factory()->create(['status' => 'in_progress']);
    $game = Game::factory()->create([
        'show_id' => $show->id,
        'type' => 'millionaire',
        'status' => 'active',
    ]);
    
    // Create 10 players
    $players = Player::factory()->count(10)->create([
        'show_id' => $show->id,
        'status' => 'active',
    ]);
    
    // Create questions
    $questions = MillionaireQuestion::factory()->count(5)->create([
        'game_id' => $game->id,
    ]);
    
    // Player 1 answers all correctly
    foreach ($questions as $i => $question) {
        MillionaireAnswer::create([
            'question_id' => $question->id,
            'player_id' => $players[0]->id,
            'selected_answer' => $question->correct_answer,
            'is_correct' => true,
        ]);
    }
    
    // Player 2 answers all incorrectly
    foreach ($questions as $question) {
        MillionaireAnswer::create([
            'question_id' => $question->id,
            'player_id' => $players[1]->id,
            'selected_answer' => 'Z',
            'is_correct' => false,
        ]);
    }
    
    $service = app(MillionaireService::class);
    $results = $service->complete($game);
    
    expect($results['eliminated_count'])->toBeGreaterThan(0);
    expect($players[0]->fresh()->status)->toBe('active');
    expect($players[1]->fresh()->status)->toBe('eliminated');
});
```

### Unit Test: Scoreboard Normalization

```php
<?php

use App\Services\Scoreboard\ScoreboardService;

test('millionaire score normalizes correctly', function () {
    $service = new ScoreboardService();
    
    $score = $service->normalize('millionaire', 8, ['total_questions' => 10]);
    
    expect($score)->toBe(800);
});

test('rope score caps at 1000', function () {
    $service = new ScoreboardService();
    
    $score = $service->normalize('rope', 500, []); // 500 clicks
    
    expect($score)->toBe(1000);
});

test('spell score factors in time', function () {
    $service = new ScoreboardService();
    
    $score = $service->normalize('spell', 1, [
        'time_taken_ms' => 30000,
        'time_limit_seconds' => 60,
    ]);
    
    expect($score)->toBe(500); // 50% of time used
});
```

### Unit Test: PIN Generator

```php
<?php

use App\Services\Player\PINGeneratorService;

test('generates 4-digit PIN', function () {
    $service = new PINGeneratorService();
    $pin = $service->generate();
    
    expect($pin)->toHaveLength(4);
    expect($pin)->toMatch('/^\d{4}$/');
});

test('generates unique PINs', function () {
    $service = new PINGeneratorService();
    $pins = $service->generateBatch(100);
    
    $unique = array_unique($pins);
    
    expect(count($unique))->toBe(100);
});

test('validates PIN format', function () {
    $service = new PINGeneratorService();
    
    expect($service->validate('1234'))->toBeTrue();
    expect($service->validate('12345'))->toBeFalse();
    expect($service->validate('abc'))->toBeFalse();
});
```

### Feature Test: Achievement Unlock

```php
<?php

use App\Models\{Player, Achievement, PlayerAchievement};
use App\Services\Achievement\AchievementService;

test('achievement can be unlocked', function () {
    $player = Player::factory()->create();
    $achievement = Achievement::factory()->create(['key' => 'test_achievement']);
    
    $service = app(AchievementService::class);
    $unlock = $service->unlock($player, 'test_achievement');
    
    expect($unlock)->toBeInstanceOf(PlayerAchievement::class);
    expect($unlock->player_id)->toBe($player->id);
    expect($unlock->achievement_id)->toBe($achievement->id);
});

test('achievement cannot be unlocked twice', function () {
    $player = Player::factory()->create();
    $achievement = Achievement::factory()->create(['key' => 'test_achievement']);
    
    $service = app(AchievementService::class);
    $unlock1 = $service->unlock($player, 'test_achievement');
    $unlock2 = $service->unlock($player, 'test_achievement');
    
    expect($unlock1->id)->toBe($unlock2->id);
    expect(PlayerAchievement::count())->toBe(1);
});
```

### Integration Test: WebSocket Broadcasting

```php
<?php

use App\Models\{Show, Player};
use App\Events\Show\ShowStarted;
use Illuminate\Support\Facades\Event;

test('show started event broadcasts', function () {
    Event::fake();
    
    $show = Show::factory()->create();
    
    event(new ShowStarted($show));
    
    Event::assertDispatched(ShowStarted::class, fn($e) => $e->show->id === $show->id);
});
```

## Test Database

Configure `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="CACHE_DRIVER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
```

## Factories

Create factories for all models:

```bash
php artisan make:factory ShowFactory
php artisan make:factory PlayerFactory
php artisan make:factory GameFactory
php artisan make:factory AchievementFactory
```

## Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/Auth/LoginTest.php

# Run with coverage
php artisan test --coverage

# Run with parallel execution
php artisan test --parallel
```

## CI/CD Integration

`.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: ruleta_test
          MYSQL_USER: test
          MYSQL_PASSWORD: test
          MYSQL_ROOT_PASSWORD: root
        ports:
          - 3306:3306
      
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: mbstring, pdo_mysql, redis
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run tests
        run: php artisan test --coverage
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: ruleta_test
          DB_USERNAME: test
          DB_PASSWORD: test
```

## Test Coverage Goals

- **Unit Tests**: 80%+ coverage
- **Feature Tests**: All API endpoints
- **Integration Tests**: Critical flows (join → play → win)

## Finalización

✅ Con este archivo completamos las 18 guías de implementación del backend.

**Resumen de archivos creados**:
1. Project Setup
2. Database Schema
3. Models & Relationships
4. Core Services
5. Reverb WebSocket Setup
6. Authentication & Authorization
7. Game Services Base
8-13. All Game Implementations
14. Achievement System
15. Audit System
16. Instructions System
17. Scoreboard System
18. Testing Strategy

## Próximo Paso

→ Actualizar `README_INDEX.md` con sección de Implementation
