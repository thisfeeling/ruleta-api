#!/bin/bash
set -e

cd /app

# Instalar dependencias de Composer
composer install --no-interaction --optimize-autoloader --no-dev

# Instalar dependencias de Node (incluye devDependencies para poder ejecutar Vite)
# Forzar la instalación de devDependencies incluso si npm está en modo production
# Usar la variable de entorno npm_config_production=false es compatible con más versiones de npm
npm_config_production=false npm ci --no-audit --no-fund

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
