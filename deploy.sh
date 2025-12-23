#!/bin/bash
# deploy.sh
set -e

cd /app

# Instalar dependencias de Composer si faltan
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --optimize-autoloader --no-dev --prefer-dist
else
    echo "Vendor exists, skipping composer install"
fi

# Instalar dependencias de Node y compilar assets si build no existe
if [ ! -d public/build ]; then
    npm_config_production=false npm ci --no-audit --no-fund
    npm run build
else
    echo "Assets exist, skipping npm install and build"
fi

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
