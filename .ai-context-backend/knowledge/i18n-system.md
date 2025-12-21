# i18n System - Backend

## Descripción General

Sistema de internacionalización (i18n) del backend que soporta múltiples idiomas para respuestas API, validaciones, audios TTS, y seeders. Aunque la mayoría del contenido bilingüe vive en el frontend (`src/locales/`), el backend gestiona:

1. **Locale Detection**: Middleware que detecta idioma via header `Accept-Language`
2. **API Localized Responses**: Error messages y validations en el idioma del usuario
3. **Database Multilingual Content**: Campos `_es` y `_en` en tablas (`achievements`, `game_instructions`)
4. **TTS Integration**: ElevenLabs audios en español colombiano
5. **Seeders Bilingües**: Populate DB con contenido en ambos idiomas

## Locales Soportados

- **es-CO**: Español Colombia (default, tono colombiano familiar)
- **en-US**: English US (fallback)

## Arquitectura

```
┌────────────────────────────────────────────────────────┐
│            Cliente envía request HTTP                   │
│  Header: Accept-Language: es-CO, en-US;q=0.9           │
└──────────────────┬─────────────────────────────────────┘
                   │
                   v
┌────────────────────────────────────────────────────────┐
│         Middleware: SetLocale                           │
│  1. Parse Accept-Language header                        │
│  2. Determinar mejor match (es-CO o en-US)              │
│  3. app()->setLocale($locale)                           │
└──────────────────┬─────────────────────────────────────┘
                   │
                   v
┌────────────────────────────────────────────────────────┐
│           Laravel Controllers/Services                  │
│  - __('messages.welcome') → "¡Bienvenido!" o "Welcome!"│
│  - Validator messages localizados                       │
│  - Queries DB: SELECT name_es / name_en según locale   │
└──────────────────┬─────────────────────────────────────┘
                   │
                   v
┌────────────────────────────────────────────────────────┐
│              API Response JSON                          │
│  {                                                       │
│    "message": "¡Bienvenido!",  ← localizado            │
│    "errors": {"field": "Este campo es requerido"}      │
│  }                                                       │
└─────────────────────────────────────────────────────────┘
```

## Middleware: `SetLocale`

### Ubicación

```
app/Http/Middleware/SetLocale.php
```

### Implementación

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locales soportados
     */
    protected array $supportedLocales = ['es', 'en'];
    protected array $localeVariants = [
        'es' => ['es-CO', 'es-ES', 'es-MX', 'es-AR'],
        'en' => ['en-US', 'en-GB']
    ];
    
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->detectLocale($request);
        app()->setLocale($locale);
        
        return $next($request);
    }
    
    /**
     * Detecta el locale desde header Accept-Language
     */
    protected function detectLocale(Request $request): string
    {
        // 1. Desde query param ?locale=es (para testing)
        if ($request->has('locale') && in_array($request->query('locale'), $this->supportedLocales)) {
            return $request->query('locale');
        }
        
        // 2. Desde header Accept-Language
        $acceptLanguage = $request->header('Accept-Language', 'es-CO');
        
        // Parse header (ej: "es-CO,en-US;q=0.9,en;q=0.8")
        foreach (explode(',', $acceptLanguage) as $lang) {
            $lang = trim(explode(';', $lang)[0]); // Remove quality factor
            
            // Match directo (es-CO)
            if (in_array($lang, $this->supportedLocales)) {
                return $lang;
            }
            
            // Match variante (es-CO → es)
            foreach ($this->localeVariants as $baseLocale => $variants) {
                if (in_array($lang, $variants)) {
                    return $baseLocale;
                }
            }
            
            // Match base (es)
            $baseLang = substr($lang, 0, 2);
            if (in_array($baseLang, $this->supportedLocales)) {
                return $baseLang;
            }
        }
        
        // 3. Default: español colombiano
        return 'es';
    }
}
```

### Registro en `app/Http/Kernel.php`

```php
protected $middleware = [
    // ...
    \App\Http\Middleware\SetLocale::class,
];
```

## Archivos de Traducción Laravel

### Ubicación

```
resources/lang/
├── es/
│   ├── messages.php
│   ├── validation.php
│   └── auth.php
└── en/
    ├── messages.php
    ├── validation.php
    └── auth.php
```

### Ejemplo: `resources/lang/es/messages.php`

```php
<?php

return [
    // Welcome
    'welcome' => '¡Bienvenido a Ruleta Familiar!',
    'goodbye' => '¡Hasta pronto!',
    
    // Player
    'player_joined' => 'Jugador :number se unió al show',
    'player_eliminated' => 'Jugador :number fue eliminado',
    'player_reconnected' => 'Jugador :number reconectó',
    
    // Games
    'game_started' => 'El juego :game ha iniciado',
    'game_ended' => 'El juego :game ha terminado',
    
    // Errors
    'invalid_pin' => 'PIN incorrecto. Intenta de nuevo.',
    'show_full' => 'El show está lleno (máximo 50 jugadores)',
    'already_eliminated' => 'Ya fuiste eliminado de este show',
    
    // Success
    'answer_correct' => '¡Respuesta correcta!',
    'achievement_unlocked' => '¡Logro desbloqueado: :name!',
];
```

### Uso en Controllers

```php
// app/Http/Controllers/PlayerController.php

public function join(Request $request)
{
    // ...
    return response()->json([
        'message' => __('messages.welcome'),
        'player' => $player
    ]);
}

public function reconnect(Request $request)
{
    if (!$player) {
        return response()->json([
            'error' => __('messages.invalid_pin')
        ], 404);
    }
    
    // ...
}
```

## Base de Datos: Campos Multilingües

### Patrón: Columnas `_es` y `_en`

Tablas que requieren contenido bilingüe:

```sql
-- achievements
name_es VARCHAR(255) NOT NULL
name_en VARCHAR(255) NOT NULL
description_es TEXT NOT NULL
description_en TEXT NOT NULL

-- game_instructions
title_es VARCHAR(255) NOT NULL
title_en VARCHAR(255) NOT NULL
content_es TEXT NOT NULL
content_en TEXT NOT NULL
```

### Accessors en Modelos Eloquent

```php
<?php

namespace App\Models;

class Achievement extends Model
{
    /**
     * Obtiene el nombre en el idioma actual
     */
    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'es' ? $this->name_es : $this->name_en;
    }
    
    /**
     * Obtiene la descripción en el idioma actual
     */
    public function getDescriptionAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'es' ? $this->description_es : $this->description_en;
    }
}
```

### API Response con Locale

```php
// GET /api/achievements
// Accept-Language: es-CO

{
    "data": [
        {
            "id": 1,
            "key": "first_steps",
            "name": "Primeros Pasos",  ← accessor dinámico
            "description": "¡Bienvenido al juego!",
            "icon": "👋",
            "points": 10
        }
    ]
}

// Accept-Language: en-US

{
    "data": [
        {
            "id": 1,
            "key": "first_steps",
            "name": "First Steps",  ← accessor dinámico
            "description": "Welcome to the game!",
            "icon": "👋",
            "points": 10
        }
    ]
}
```

## TTS Integration (ElevenLabs)

### Español Colombiano

Configuración para narración con acento colombiano:

```php
// config/services.php

'elevenlabs' => [
    'api_key' => env('ELEVENLABS_API_KEY'),
    'voice_id' => env('ELEVENLABS_VOICE_ID', 'pNInz6obpgDQGcFmaJgB'), // Adam voice
    'model' => 'eleven_multilingual_v2',
    'language_code' => 'es', // Español
    'stability' => 0.5,
    'similarity_boost' => 0.75,
    'style' => 0.3,
];
```

### Generación de Audios

```php
// app/Services/Audio/TTSService.php

public function generateNarration(string $text, string $locale = 'es'): string
{
    if ($locale === 'es') {
        // ElevenLabs API con voice en español
        $response = Http::withHeaders([
            'xi-api-key' => config('services.elevenlabs.api_key'),
        ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
            'text' => $text,
            'model_id' => 'eleven_multilingual_v2',
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.75,
                'style' => 0.3,
                'use_speaker_boost' => true
            ]
        ]);
        
        // Upload a S3
        $audioContent = $response->body();
        $s3Key = "narration/{$locale}/" . md5($text) . '.mp3';
        Storage::disk('s3')->put($s3Key, $audioContent);
        
        return Storage::disk('s3')->url($s3Key);
    }
    
    // Fallback: usar otro TTS o silence
    return null;
}
```

## Seeders Bilingües

### AchievementSeeder

```php
<?php

namespace Database\Seeders;

use App\Models\Achievement;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'key' => 'first_steps',
                'name_es' => 'Primeros Pasos',
                'name_en' => 'First Steps',
                'description_es' => '¡Bienvenido al juego! Has dado tus primeros pasos.',
                'description_en' => 'Welcome to the game! You\'ve taken your first steps.',
                'icon' => '👋',
                'points' => 10,
                // ...
            ],
            
            [
                'key' => 'champion',
                'name_es' => '¡Campeón!',
                'name_en' => 'Champion!',
                'description_es' => 'Ganaste la Ruleta Final y eres el campeón del show.',
                'description_en' => 'You won the Final Roulette and are the show champion.',
                'icon' => '👑',
                'points' => 1000,
                // ...
            ],
            
            // ... (35+ achievements)
        ];
        
        foreach ($achievements as $achievement) {
            Achievement::create($achievement);
        }
    }
}
```

### GameInstructionSeeder

```php
[
    'game_type' => 'millionaire',
    'title_es' => '¿Quién Quiere Ser Millonario?',
    'title_en' => 'Who Wants to Be a Millionaire?',
    'content_es' => "**Objetivo**: Responde preguntas de cultura general...",
    'content_en' => "**Objective**: Answer general knowledge questions...",
    // ...
],
```

## Validation Messages Localizadas

Laravel ya incluye traducciones para validaciones:

```
resources/lang/es/validation.php
resources/lang/en/validation.php
```

### Uso Automático

```php
// app/Http/Requests/JoinShowRequest.php

public function rules()
{
    return [
        'nickname' => 'required|string|max:50',
        'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
    ];
}

// Si falla con Accept-Language: es-CO
{
    "errors": {
        "nickname": ["El campo nickname es obligatorio."],
        "gender": ["El género seleccionado es inválido."]
    }
}

// Si falla con Accept-Language: en-US
{
    "errors": {
        "nickname": ["The nickname field is required."],
        "gender": ["The selected gender is invalid."]
    }
}
```

## Endpoints API Relacionados

### `GET /api/locales`

Lista locales soportados.

**Response**:
```json
{
    "supported": ["es", "en"],
    "default": "es",
    "variants": {
        "es": ["es-CO", "es-ES", "es-MX"],
        "en": ["en-US", "en-GB"]
    }
}
```

### `GET /api/locales/{locale}`

Obtiene metadata de un locale (no translations, solo info).

**Response**:
```json
{
    "code": "es-CO",
    "name": "Español (Colombia)",
    "native_name": "Español (Colombia)",
    "flag": "🇨🇴",
    "direction": "ltr"
}
```

**Nota**: Las traducciones reales viven en el frontend (`src/locales/es-CO.json`, `src/locales/en-US.json`).

## Frontend Integration

El frontend tiene sus propias traducciones en:

```
src/locales/
├── es-CO.json  (240+ keys)
└── en-US.json  (240+ keys)
```

Pero para **error messages API** y **validaciones**, usa las respuestas localizadas del backend:

```typescript
// Frontend: api.service.ts
const response = await fetch('/api/players/join', {
  method: 'POST',
  headers: {
    'Accept-Language': i18n.global.locale.value, // 'es-CO' o 'en-US'
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(data)
});

if (!response.ok) {
  const error = await response.json();
  // error.message ya está localizado por el backend
  toast.error(error.message);
}
```

## Testing

### Unit Tests

```php
// tests/Unit/LocaleMiddlewareTest.php

public function test_detects_locale_from_header()
{
    $request = Request::create('/api/achievements', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'es-CO,en-US;q=0.9'
    ]);
    
    $middleware = new SetLocale();
    $middleware->handle($request, function($req) {
        $this->assertEquals('es', app()->getLocale());
    });
}

public function test_fallbacks_to_base_locale()
{
    $request = Request::create('/api/achievements', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'es-AR,fr;q=0.8' // es-AR → es
    ]);
    
    $middleware = new SetLocale();
    $middleware->handle($request, function($req) {
        $this->assertEquals('es', app()->getLocale());
    });
}
```

### Integration Tests

```php
// tests/Feature/LocalizedAPITest.php

public function test_api_returns_localized_messages()
{
    $response = $this->withHeaders([
        'Accept-Language' => 'en-US'
    ])->post('/api/players/join', [
        // Invalid data
    ]);
    
    $response->assertStatus(422)
        ->assertJson([
            'errors' => [
                'nickname' => ['The nickname field is required.'] // En inglés
            ]
        ]);
}
```

## Consideraciones de Performance

### 1. Caching de Locale

No cachear locale por usuario, siempre resolver por request (stateless).

### 2. Database Queries

Usar accessors (no condicionales inline):

```php
// ❌ Malo
$name = app()->getLocale() === 'es' ? $achievement->name_es : $achievement->name_en;

// ✅ Bueno
$name = $achievement->name; // Accessor dinámico
```

### 3. TTS Audios

Cachear audios generados en S3 por hash del texto:

```php
$cacheKey = md5($text . $locale);
if (Storage::disk('s3')->exists("narration/{$locale}/{$cacheKey}.mp3")) {
    return Storage::disk('s3')->url("narration/{$locale}/{$cacheKey}.mp3");
}
```

## Futuras Mejoras

1. **Más Locales**: pt-BR (portugués), fr-FR (francés)
2. **User Preference**: Persistir idioma preferido del jugador en DB
3. **A/B Testing**: Probar diferentes versiones de textos
4. **RTL Support**: Árabe, hebreo (direction: rtl)
5. **Crowdin Integration**: Plataforma de traducción colaborativa

---

**Ver también**:
- Frontend: `src/locales/es-CO.json`, `src/locales/en-US.json`
- `achievement-system.md` - Seeders bilingües de logros
- `instructions-system.md` - Instrucciones de juegos bilingües

**Última actualización**: Diciembre 2025
