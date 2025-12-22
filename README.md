# Ruleta Familiar - Backend API

Backend API for **Ruleta Familiar**, a real-time multiplayer game show inspired by SquidCraft, built with Laravel 12, Reverb (WebSockets), and designed for 30-50 simultaneous players.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **WebSockets**: Laravel Reverb
- **Database**: MySQL 8 / MariaDB
- **Cache/Queue**: Redis
- **Storage**: RustFS (S3-compatible)
- **Audio**: ElevenLabs API / TopMediai API
- **Deployment**: Docker + Dokploy + Traefik

## Prerequisites

### Local Development
- PHP 8.2+ with extensions: redis, mbstring, curl, pdo, bcmath, tokenizer, dom, gd, fileinfo, xml, zip
- Composer 2.x
- Node.js 22+
- MySQL 8.0+ or MariaDB (local or Docker)
- Redis (local or Docker)

### Production (Dokploy)
- Dokploy configured with Traefik
- MySQL database (external)
- Redis (can be in container)
- Domain configured in Traefik for automatic SSL

## Quick Start

### 1. Clone and Install Dependencies

```bash
git clone <repository-url>
cd ruleta-api
composer install
npm install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure:
- Database credentials
- Redis connection
- RustFS/S3 credentials
- ElevenLabs API key
- Frontend URL for CORS

### 3. Database Setup

```bash
php artisan migrate
php artisan db:seed
```

### 4. Run Development Servers

**Option A: Multiple terminals**

```bash
# Terminal 1: Laravel server
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2: Reverb WebSocket server
php artisan reverb:start --host=0.0.0.0 --port=8080

# Terminal 3: Queue worker
php artisan queue:work redis --sleep=3 --tries=3

# Terminal 4 (optional): Vite dev server
npm run dev
```

**Option B: Using composer script**

```bash
composer dev
```

## Project Structure

```
app/
├── Domain/              # Domain-driven design layers
│   ├── Show/           # Show management
│   ├── Game/           # Game logic (6 games)
│   ├── Player/         # Player management
│   ├── Audio/          # Audio system
│   ├── Achievement/    # 35+ achievements
│   └── Audit/          # Audit logging
├── Services/           # Application services
├── Events/             # WebSocket events (28+)
├── Http/Controllers/   # API controllers
└── Models/             # Eloquent models
```

## Features

### Core Features
- 🎮 **6 Games**: 4 elimination games + 2 bonus games
- 🏆 **Achievement System**: 35+ unlockable achievements
- 📊 **Unified Scoreboard**: Normalized scoring across all games
- 🔊 **3-Channel Audio**: Music, SFX, and voice with independent volume control
- 🌐 **Real-time WebSockets**: 28+ event types via Reverb
- 🔐 **PIN Reconnection**: 4-digit PIN for reconnecting players
- 📝 **Audit System**: Dual storage (DB 30 days + S3 permanent)
- 🌍 **i18n Support**: Spanish (Colombian) + English

### Games
1. **Millionaire** - Q&A game (Who Wants to Be a Millionaire style)
2. **The Rope** - Group click competition with 3D visualization
3. **Spell It** - Word spelling with audio validation
4. **The Wheel** (Final) - Cumulative points roulette
5. **Word Search** (Bonus) - 15×15 word search, non-eliminatory
6. **Flappy Bird** (Bonus) - Survival game, non-eliminatory

## Development

### Running Tests

```bash
php artisan test
```

### Code Style

```bash
./vendor/bin/pint
```

### View Logs

```bash
php artisan pail
```

## Production Deployment (Dokploy)

### Environment Variables

Set these in Dokploy:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
DB_HOST=<mysql-host>
DB_DATABASE=<database>
DB_USERNAME=<username>
DB_PASSWORD=<password>
REVERB_APP_KEY=<production-key>
REVERB_APP_SECRET=<production-secret>
FRONTEND_URL=https://yourdomain.com
```

### Traefik Configuration

Configure 2 routers in Dokploy:

1. **API Router** (port 80)
   - Host: `api.yourdomain.com`
   - Container port: 80
   - SSL: Automatic

2. **WebSocket Router** (port 8080)
   - Host: `api.yourdomain.com` or `ws.yourdomain.com`
   - Container port: 8080
   - SSL: Automatic
   - Headers: Configure `Upgrade` and `Connection` for WebSocket

### Deploy

Dokploy will automatically:
1. Build Docker image
2. Run migrations
3. Start Supervisor (Nginx + PHP-FPM + Reverb + Queue Workers + Redis)
4. Configure SSL via Traefik

## Documentation

Full documentation available in `.ai-context-backend/`:

- **Goals**: Project overview and objectives
- **Implementation**: Step-by-step implementation guides (18 steps)
- **Knowledge**: Technical documentation (architecture, systems, schemas)
- **Rules**: Implementation rules and contracts

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register player
- `POST /api/auth/login` - Login with PIN
- `POST /api/auth/logout` - Logout

### Game Flow
- `GET /api/shows` - List shows
- `POST /api/shows` - Create show (supervisor)
- `GET /api/shows/{id}` - Show details
- `POST /api/shows/{id}/start` - Start show
- `GET /api/games/{id}` - Game state

### WebSocket Events
Connect to: `ws://localhost:8080` (dev) or `wss://api.yourdomain.com` (prod)

See `.ai-context-backend/knowledge/reverb-websockets.md` for full event list.

## License

MIT License

---

**Version**: 1.0  
**Last Updated**: December 2025

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
