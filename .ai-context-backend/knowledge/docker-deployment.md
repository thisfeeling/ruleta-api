# Docker Deployment - Ruleta Familiar

## Dockerfile - Laravel + Reverb

```dockerfile
FROM php:8.3-fpm

# Dependencias
RUN apt-get update && apt-get install -y \
    git unzip supervisor \
    libpng-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache

# Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord", "-n"]
```

## supervisord.conf

```conf
[supervisord]
nodaemon=true

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
priority=10
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr
stdout_maxbytes=0
stderr_maxbytes=0

[program:reverb]
command=php artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/html
autostart=true
autorestart=true
priority=20
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr
stdout_maxbytes=0
stderr_maxbytes=0

[program:queue-worker]
command=php artisan queue:work --sleep=3 --tries=3
directory=/var/www/html
autostart=true
autorestart=true
priority=30
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr
stdout_maxbytes=0
stderr_maxbytes=0
```

## docker-compose.yml

```yaml
services:
  laravel:
    build:
      context: .
      dockerfile: docker/Dockerfile
    ports:
      - "9000:9000"
      - "8080:8080"
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    volumes:
      - ./storage:/var/www/html/storage
    depends_on:
      - mysql
      - redis
    networks:
      - ruleta

  mysql:
    image: mysql:8
    environment:
      MYSQL_DATABASE: ruleta
      MYSQL_ROOT_PASSWORD: secret
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - ruleta

  redis:
    image: redis:alpine
    networks:
      - ruleta

networks:
  ruleta:
    driver: bridge

volumes:
  mysql_data:
```

## Dokploy

1. Crear app en Dokploy
2. Conectar repositorio GitHub
3. Configurar variables de entorno
4. Deploy automático on push

Traefik se configura automáticamente.

**Ver también**: `technical-goals.md`
