# 01 - Project Setup

**Status**: [x] Completed

## Objetivo

Configurar el proyecto Laravel 12 para desarrollo local (sin Docker) y producción en Dokploy con Nginx, Supervisor, Reverb y Redis.

## Prerrequisitos

### Desarrollo Local
- PHP 8.4+ con extensiones (redis, mbstring, curl, pdo, bcmath, tokenizer, dom, gd, fileinfo, xml, zip)
- Composer 2.x
- Node.js 22+
- MySQL 8.0+ (instalado localmente o en Docker)
- Redis (instalado localmente o en Docker)

### Producción (Dokploy)
- Dokploy configurado con Traefik
- Base de datos MySQL externa
- Redis (puede ir en el contenedor)
- Dominio configurado en Traefik para SSL automático

## Pasos de Implementación

### 1.1 Laravel 12 Base Setup

```bash
# Crear proyecto Laravel 12
composer create-project laravel/laravel ruleta-api

cd ruleta-api

# Instalar dependencias clave
composer require laravel/reverb
composer require spatie/laravel-medialibrary
composer require spatie/laravel-permission
composer require aws/aws-sdk-php
composer require predis/predis

# Dev dependencies
composer require --dev laravel/pint
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel
```

**Sincronización Frontend**: El frontend conectará a `wss://api.tudominio.com` para Reverb.

### 1.2 Environment Configuration

#### `.env` para Desarrollo Local

```env
APP_NAME="Ruleta"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=America/Bogota
APP_URL=http://localhost:8000
APP_LOCALE=es_CO
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ruleta_app
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=redis
SESSION_CONNECTION=default
SESSION_STORE=redis
SESSION_TABLE=sessions
SESSION_SECURE_COOKIE=false
SESSION_LIFETIME=120
SESSION_COOKIE=ruleta_app_session
SESSION_PARTITIONED_COOKIE=false
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=localhost
SESSION_EXPIRE_ON_CLOSE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=rustfs
QUEUE_CONNECTION=redis

CACHE_STORE=redis
CACHE_PREFIX=cache_ruleta_app_

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=
REDIS_PORT=6379
REDIS_URL=redis://127.0.0.1:6379
REDIS_USERNAME=default

# Reverb WebSocket Server (local)
REVERB_APP_ID=ruleta-app
REVERB_APP_KEY=local-app-key
REVERB_APP_SECRET=local-app-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

# Frontend URL (CORS)
WEB_APP_URL=http://localhost:5173
ORIGINS=http://localhost:5173,http://localhost:8000

# S3 Storage (RustFS)
RUSTFS_ACCESS_KEY_ID=
RUSTFS_SECRET_ACCESS_KEY=
RUSTFS_DEFAULT_REGION=us-east-1
RUSTFS_BUCKET=ruleta-storage
RUSTFS_URL=http://localhost:9000/ruleta-storage
RUSTFS_ENDPOINT=http://localhost:9000
RUSTFS_USE_PATH_STYLE_ENDPOINT=true

# ElevenLabs TTS
ELEVENLABS_API_KEY=
ELEVENLABS_VOICE_ID=narrator-voice-id

# Audit retention
AUDIT_DB_RETENTION_DAYS=30
```

#### `.env` para Producción (Dokploy)

```env
APP_NAME="Ruleta"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=America/Bogota
APP_URL=https://api.tudominio.com
APP_LOCALE=es_CO
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=mysql-host
DB_PORT=3306
DB_DATABASE=ruleta_api
DB_USERNAME=ruleta_user
DB_PASSWORD=secure_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Reverb WebSocket Server (producción)
REVERB_APP_ID=ruleta-app
REVERB_APP_KEY=production-app-key
REVERB_APP_SECRET=production-app-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

# Frontend URL (CORS)
WEB_APP_URL=https://tudominio.com

# S3 Storage (RustFS)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=ruleta-storage
AWS_URL=https://rustfs.example.com
AWS_ENDPOINT=https://rustfs.example.com
AWS_USE_PATH_STYLE_ENDPOINT=true

# ElevenLabs TTS
ELEVENLABS_API_KEY=
ELEVENLABS_VOICE_ID=narrator-voice-id

# Audit retention
AUDIT_DB_RETENTION_DAYS=30

# Nixpacks variables (para Dokploy)
PORT=80
NIXPACKS_PHP_ROOT_DIR=/app/public
NIXPACKS_PHP_FALLBACK_PATH=/index.php
IS_LARAVEL=true
```

### 1.3 Dockerfile para Dokploy (Producción)

Crear `Dockerfile`:

```dockerfile
FROM nixos/nix:latest AS builder

# Instalar paquetes necesarios
RUN nix-channel --update && \
    nix-env -iA nixpkgs.php84 \
    nixpkgs.php84Extensions.redis \
    nixpkgs.php84Extensions.mbstring \
    nixpkgs.php84Extensions.curl \
    nixpkgs.php84Extensions.pdo \
    nixpkgs.php84Extensions.bcmath \
    nixpkgs.php84Extensions.tokenizer \
    nixpkgs.php84Extensions.dom \
    nixpkgs.php84Extensions.gd \
    nixpkgs.php84Extensions.fileinfo \
    nixpkgs.php84Extensions.xml \
    nixpkgs.php84Extensions.zip \
    nixpkgs.phpPackages.composer \
    nixpkgs.nodejs_22 \
    nixpkgs.python311Packages.supervisor \
    nixpkgs.nginx \
    nixpkgs.libmysqlclient \
    nixpkgs.curl \
    nixpkgs.wget \
    nixpkgs.redis

FROM debian:bookworm-slim

# Copiar binarios de Nix
COPY --from=builder /nix /nix

# Establecer PATH
ENV PATH="/nix/var/nix/profiles/default/bin:${PATH}"

# Crear usuario www-data
RUN groupadd -g 33 www-data && \
    useradd -u 33 -g www-data -s /bin/bash -m www-data

# Crear directorios necesarios
RUN mkdir -p /app /assets /var/log /etc/supervisor/conf.d /etc/nginx

WORKDIR /app

# Copiar archivos de la aplicación
COPY . /app

# Copiar scripts y configuraciones
COPY .docker/start.sh /assets/start.sh
COPY .docker/supervisord.conf /etc/supervisord.conf
COPY .docker/worker-*.conf /etc/supervisor/conf.d/
COPY .docker/nginx.template.conf /assets/nginx.template.conf
COPY .docker/php-fpm.conf /assets/php-fpm.conf
COPY .docker/prestart.mjs /assets/scripts/prestart.mjs
COPY .docker/setup-php-extensions.sh /assets/setup-php-extensions.sh
COPY deploy.sh /app/deploy.sh

# Hacer ejecutables los scripts
RUN chmod +x /assets/start.sh \
    /assets/setup-php-extensions.sh \
    /app/deploy.sh

# Configurar extensiones PHP
RUN bash /assets/setup-php-extensions.sh

# Ejecutar deploy
RUN bash /app/deploy.sh

# Establecer permisos
RUN chown -R www-data:www-data /app \
    && chmod -R 775 /app/storage \
    && chmod -R 775 /app/bootstrap/cache

EXPOSE 80

CMD ["/assets/start.sh"]
```

### 1.4 Scripts y Configuraciones de Producción

#### `.docker/setup-php-extensions.sh`

```bash
#!/bin/bash
set -e

# Configurar extensiones PHP adicionales si es necesario
PHP_INI_DIR=$(php -i | grep 'additional .ini files' | awk '{print $NF}')
mkdir -p "$PHP_INI_DIR"

echo "PHP extensions configured successfully"
```

#### `.docker/deploy.sh`

```bash
#!/bin/bash
set -e

cd /app

# Instalar dependencias de Composer
composer install --no-interaction --optimize-autoloader --no-dev

# Instalar dependencias de Node
npm ci --production

# Compilar assets
npm run build

# Limpiar caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar migraciones (opcional - comentar si se hace manualmente)
# php artisan migrate --force

echo "Deploy completed successfully"
```

#### `.docker/start.sh`

```bash
#!/bin/bash

# Transform the nginx configuration
node /assets/scripts/prestart.mjs /assets/nginx.template.conf /etc/nginx.conf

# Start supervisor
supervisord -c /etc/supervisord.conf -n
```

#### `.docker/prestart.mjs`

```javascript
import { readFileSync, writeFileSync } from 'fs';

const [,, templatePath, outputPath] = process.argv;

let config = readFileSync(templatePath, 'utf-8');

// Replace environment variables
config = config.replace(/\$\{PORT\}/g, process.env.PORT || '80');
config = config.replace(/\$\{NIXPACKS_PHP_ROOT_DIR\}/g, process.env.NIXPACKS_PHP_ROOT_DIR || '/app/public');
config = config.replace(/\$\{NIXPACKS_PHP_FALLBACK_PATH\}/g, process.env.NIXPACKS_PHP_FALLBACK_PATH || '/index.php');

// Handle conditional blocks
if (process.env.IS_LARAVEL === 'true') {
    config = config.replace(/\$if\(IS_LARAVEL\) \(([\s\S]*?)\) else \(\)/g, '$1');
} else {
    config = config.replace(/\$if\(IS_LARAVEL\) \([\s\S]*?\) else \(([\s\S]*?)\)/g, '$1');
}

if (process.env.NIXPACKS_PHP_ROOT_DIR) {
    config = config.replace(/\$if\(NIXPACKS_PHP_ROOT_DIR\) \(([\s\S]*?)\) else \([\s\S]*?\)/g, '$1');
} else {
    config = config.replace(/\$if\(NIXPACKS_PHP_ROOT_DIR\) \([\s\S]*?\) else \(([\s\S]*?)\)/g, '$1');
}

if (process.env.NIXPACKS_PHP_FALLBACK_PATH) {
    config = config.replace(/\$if\(NIXPACKS_PHP_FALLBACK_PATH\) \(([\s\S]*?)\) else \([\s\S]*?\)/g, '$1');
} else {
    config = config.replace(/\$if\(NIXPACKS_PHP_FALLBACK_PATH\) \([\s\S]*?\) else \(([\s\S]*?)\)/g, '$1');
}

// Replace nginx paths
config = config.replace(/\$!\{nginx\}/g, '/nix/var/nix/profiles/default');

writeFileSync(outputPath, config);
console.log('Nginx configuration generated successfully');
```

#### `.docker/supervisord.conf`

```ini
[unix_http_server]
file=/assets/supervisor.sock

[supervisord]
logfile=/var/log/supervisord.log
logfile_maxbytes=50MB
logfile_backups=10
loglevel=info
pidfile=/assets/supervisord.pid
nodaemon=false
silent=false
minfds=1024
minprocs=200

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[supervisorctl]
serverurl=unix:///assets/supervisor.sock

[include]
files = /etc/supervisor/conf.d/*.conf
```

#### `.docker/worker-nginx.conf`

```ini
[program:worker-nginx]
process_name=%(program_name)s_%(process_num)02d
command=nginx -c /etc/nginx.conf
autostart=true
autorestart=true
stdout_logfile=/var/log/worker-nginx.log
stderr_logfile=/var/log/worker-nginx.log
```

#### `.docker/worker-phpfpm.conf`

```ini
[program:worker-phpfpm]
process_name=%(program_name)s_%(process_num)02d
command=php-fpm -y /assets/php-fpm.conf -F
autostart=true
autorestart=true
stdout_logfile=/var/log/worker-phpfpm.log
stderr_logfile=/var/log/worker-phpfpm.log
```

#### `.docker/worker-laravel.conf`

```ini
[program:worker-laravel]
process_name=%(program_name)s_%(process_num)02d
command=bash -c 'exec php /app/artisan queue:work --sleep=3 --tries=3 --max-time=3600'
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
startsecs=0
stopwaitsecs=3600
stdout_logfile=/var/log/worker-laravel.log
stderr_logfile=/var/log/worker-laravel.log
```

#### `.docker/worker-reverb.conf`

```ini
[program:worker-reverb]
process_name=%(program_name)s_%(process_num)02d
command=bash -c 'exec php /app/artisan reverb:start --host=0.0.0.0 --port=8080'
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
startsecs=0
stdout_logfile=/var/log/worker-reverb.log
stderr_logfile=/var/log/worker-reverb.log
```

#### `.docker/worker-redis.conf`

```ini
[program:worker-redis]
command=redis-server --protected-mode no
autostart=true
autorestart=true
stdout_logfile=/var/log/worker-redis.log
stderr_logfile=/var/log/worker-redis.log
```

#### `.docker/php-fpm.conf`

```ini
[www]
listen = 127.0.0.1:9000
user = www-data
group = www-data
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.min_spare_servers = 4
pm.max_spare_servers = 32
pm.start_servers = 18
clear_env = no
php_admin_value[post_max_size] = 35M
php_admin_value[upload_max_filesize] = 30M
```

#### `.docker/nginx.template.conf`

```nginx
user www-data www-data;
worker_processes 5;
daemon off;

worker_rlimit_nofile 8192;

events {
  worker_connections  4096;
}

http {
    include    $!{nginx}/conf/mime.types;
    index    index.html index.htm index.php;

    default_type application/octet-stream;
    log_format   main '$remote_addr - $remote_user [$time_local]  $status '
        '"$request" $body_bytes_sent "$http_referer" '
        '"$http_user_agent" "$http_x_forwarded_for"';
    access_log /var/log/nginx-access.log;
    error_log /var/log/nginx-error.log;
    sendfile     on;
    tcp_nopush   on;
    server_names_hash_bucket_size 128;

    # Servidor principal (Laravel)
    server {
        listen ${PORT};
        listen [::]:${PORT};
        server_name _;

        $if(NIXPACKS_PHP_ROOT_DIR) (
            root ${NIXPACKS_PHP_ROOT_DIR};
        ) else (
            root /app/public;
        )

        add_header X-Content-Type-Options "nosniff";

        client_max_body_size 35M;

        index index.php;

        charset utf-8;

        $if(NIXPACKS_PHP_FALLBACK_PATH) (
            location / {
                try_files $uri $uri/ ${NIXPACKS_PHP_FALLBACK_PATH}?$query_string;
            }
        ) else (
          location / {
                try_files $uri $uri/ /index.php?$query_string;
           }
        )

        location = /favicon.ico { access_log off; log_not_found off; }
        location = /robots.txt  { access_log off; log_not_found off; }

        $if(IS_LARAVEL) (
            error_page 404 /index.php;
        ) else ()

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include $!{nginx}/conf/fastcgi_params;
            include $!{nginx}/conf/fastcgi.conf;
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }
    }

    # Servidor WebSocket (Reverb)
    # Traefik manejará SSL y proxy a este puerto
    server {
        listen 8080;
        listen [::]:8080;
        server_name _;

        location / {
            proxy_pass http://127.0.0.1:8080;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "Upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_read_timeout 86400;
        }
    }
}
```

### 1.5 Configuración de Traefik en Dokploy

Configurar en Dokploy los siguientes routers en Traefik:

**Router 1 - API Principal (puerto 80)**
- Host: `api.tudominio.com`
- Puerto del contenedor: `80`
- Path: `/`
- SSL: Automático

**Router 2 - WebSocket Reverb (puerto 8080)**
- Host: `api.tudominio.com` o `ws.tudominio.com`
- Puerto del contenedor: `8080`
- Path: `/app` (o configurar según Reverb)
- SSL: Automático
- Headers: Configurar `Upgrade` y `Connection` para WebSocket

### 1.6 Directory Structure Setup

Crear estructura de carpetas DDD:

```bash
# Domain folders
mkdir -p app/Domain/Show
mkdir -p app/Domain/Show/Actions
mkdir -p app/Domain/Show/Services
mkdir -p app/Domain/Show/Events

mkdir -p app/Domain/Game
mkdir -p app/Domain/Game/Actions
mkdir -p app/Domain/Game/Services
mkdir -p app/Domain/Game/Events

mkdir -p app/Domain/Player
mkdir -p app/Domain/Player/Actions
mkdir -p app/Domain/Player/Services

mkdir -p app/Domain/Audio
mkdir -p app/Domain/Audio/Services

mkdir -p app/Domain/Achievement
mkdir -p app/Domain/Achievement/Services
mkdir -p app/Domain/Achievement/Triggers

mkdir -p app/Domain/Audit
mkdir -p app/Domain/Audit/Services

# Services folder
mkdir -p app/Services/Game
mkdir -p app/Services/Audio
mkdir -p app/Services/Storage
mkdir -p app/Services/TTS
mkdir -p app/Services/Achievement
mkdir -p app/Services/Audit

# Events folders
mkdir -p app/Events/Games
mkdir -p app/Events/Scoreboard
mkdir -p app/Events/Achievements
mkdir -p app/Events/Audio
mkdir -p app/Events/Instructions

# Resources
mkdir -p resources/audio/numbers
mkdir -p resources/audio/dialogs
mkdir -p resources/audio/music
```

### 1.7 Configuration Files

Actualizar `config/cors.php`:

```php
<?php

return [
    'paths' => ['api/*', 'broadcasting/auth'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

Actualizar `config/broadcasting.php`:

```php
'connections' => [
    'reverb' => [
        'driver' => 'reverb',
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ],
    ],
],
```

## Desarrollo Local

### Iniciar servicios localmente (sin Docker)

```bash
# Terminal 1: Iniciar servidor Laravel
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2: Iniciar Reverb WebSocket
php artisan reverb:start --host=0.0.0.0 --port=8080

# Terminal 3: Iniciar queue worker
php artisan queue:work redis --sleep=3 --tries=3

# Terminal 4 (opcional): Vite dev server para compilar assets
npm run dev
```

### Base de datos local con Docker (opcional)

Si prefieres usar Docker solo para MySQL y Redis:

```yaml
# docker-compose.local.yml
version: '3.8'

services:
  mysql:
    image: mysql:8.0
    container_name: ruleta_mysql_local
    environment:
      MYSQL_DATABASE: ruleta_api
      MYSQL_ROOT_PASSWORD: root
      MYSQL_ALLOW_EMPTY_PASSWORD: "yes"
    ports:
      - "3306:3306"
    volumes:
      - mysql_local_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    container_name: ruleta_redis_local
    ports:
      - "6379:6379"

volumes:
  mysql_local_data:
```

```bash
# Iniciar solo MySQL y Redis
docker-compose -f docker-compose.local.yml up -d
```

## Verificación

### Desarrollo Local

```bash
# Verificar PHP y extensiones
php -v
php -m | grep redis

# Generar app key
php artisan key:generate

# Ejecutar migraciones
php artisan migrate

# Verificar Reverb
curl http://localhost:8080

# Verificar API
curl http://localhost:8000/api
```

### Producción (Dokploy)

```bash
# Ver logs del contenedor
dokploy logs <container-id>

# Ver logs específicos de Supervisor
dokploy exec <container-id> tail -f /var/log/supervisord.log
dokploy exec <container-id> tail -f /var/log/worker-reverb.log
dokploy exec <container-id> tail -f /var/log/worker-laravel.log

# Verificar procesos de Supervisor
dokploy exec <container-id> supervisorctl status

# Verificar Nginx
dokploy exec <container-id> nginx -t

# Verificar conexión a base de datos
dokploy exec <container-id> php artisan migrate:status
```

## Sincronización con Frontend

1. **WebSocket URL (Desarrollo)**: `ws://localhost:8080`
2. **WebSocket URL (Producción)**: `wss://api.tudominio.com` (Traefik maneja SSL)
3. **API Base URL (Desarrollo)**: `http://localhost:8000/api`
4. **API Base URL (Producción)**: `https://api.tudominio.com/api`
5. **CORS**: Frontend URL debe estar en `.env` como `FRONTEND_URL`
6. **Echo Config**: El frontend usará las credenciales de Reverb:

```typescript
// Frontend Echo config (desarrollo)
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: 'local-app-key',
    wsHost: 'localhost',
    wsPort: 8080,
    wssPort: 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// Frontend Echo config (producción)
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: 'production-app-key',
    wsHost: 'api.tudominio.com',
    wsPort: 443,
    wssPort: 443,
    forceTLS: true,
    enabledTransports: ['ws', 'wss'],
});
```

## Notas Importantes

1. **Reverb en producción**: El contenedor expone Reverb en `http://0.0.0.0:8080` internamente, pero Traefik lo expone con SSL automático
2. **Redis interno**: Redis corre dentro del contenedor en producción, conectado via `127.0.0.1:6379`
3. **Nginx como proxy**: Nginx hace proxy de las peticiones WebSocket desde el puerto 8080 al proceso de Reverb
4. **Supervisor**: Maneja todos los procesos (nginx, php-fpm, laravel workers, reverb, redis)
5. **PHP 8.4**: Asegúrate de tener todas las extensiones necesarias en desarrollo local
6. **Node.js 22**: Se usa para compilar assets y para el script de prestart en producción

## Próximos Pasos

→ **02 - Database Schema**: Crear migraciones y seeders
