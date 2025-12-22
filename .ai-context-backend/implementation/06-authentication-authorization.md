# 06 - Authentication & Authorization

**Status**: [x] Completed

## Objetivo

Implementar autenticación con Sanctum, guards, policies y middleware.

## Dependencias

- **Anterior**: 05 - Reverb WebSocket Setup
- **Sincronización Frontend**: Token management, login/logout endpoints

## Implementación

### 6.1 Install Sanctum

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 6.2 Auth Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
```

### 6.3 Player Auth Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{Player, Show};
use App\Services\Player\PINGeneratorService;
use Illuminate\Http\Request;

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

        if (!$show->canAcceptPlayers()) {
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
            'player_number' => $show->current_player_count + 1,
            'pin' => $this->pinGenerator->generate(),
            'status' => 'active',
        ]);

        $show->increment('current_player_count');

        $token = $user->createToken('player-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'player' => $player,
            'token' => $token,
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

        $token = $player->user->createToken('reconnect-token')->plainTextToken;

        return response()->json([
            'user' => $player->user,
            'player' => $player,
            'token' => $token,
        ]);
    }
}
```

### 6.4 Policies

```bash
php artisan make:policy ShowPolicy --model=Show
```

```php
<?php

namespace App\Policies;

use App\Models\{User, Show};

class ShowPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Show $show): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSupervisor();
    }

    public function update(User $user, Show $show): bool
    {
        return $user->isSupervisor();
    }

    public function delete(User $user, Show $show): bool
    {
        return $user->isSupervisor();
    }

    public function control(User $user, Show $show): bool
    {
        return $user->isSupervisor();
    }
}
```

### 6.5 Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSupervisor
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isSupervisor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
```

Register in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'supervisor' => \App\Http\Middleware\EnsureSupervisor::class,
    ]);
})
```

### 6.6 API Routes

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\{AuthController, PlayerAuthController};

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/player/join', [PlayerAuthController::class, 'join']);
Route::post('/auth/player/reconnect', [PlayerAuthController::class, 'reconnect']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    
    // Supervisor only routes
    Route::middleware('supervisor')->group(function () {
        // Supervisor endpoints here
    });
});
```

## Próximos Pasos

→ **07 - Game Services Base**: Game engine, state machine
