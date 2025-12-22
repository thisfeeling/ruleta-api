FROM nixos/nix:latest AS builder

# Instalar paquetes necesarios
RUN nix-channel --update && \
    nix-env -iA nixpkgs.php84 \
    nixpkgs.php84Packages.composer \
    nixpkgs.nodejs_22 \
    nixpkgs.nginx \
    nixpkgs.python3 \
    nixpkgs.python3Packages.supervisor \
    nixpkgs.redis

# Build assets and install PHP/Node deps in builder stage
WORKDIR /app
# Copy minimal files required for installing dependencies and building assets
COPY composer.json composer.lock* package.json package-lock.json* /app/
# Composer install (no dev) and Node install/build (dev deps required for vite)
RUN composer install --no-interaction --optimize-autoloader --no-dev --prefer-dist || true && \
    npm_config_production=false npm ci --no-audit --no-fund && \
    npm run build

FROM debian:bookworm-slim

# Copiar binarios de Nix
COPY --from=builder /nix /nix

# Establecer PATH
ENV PATH="/nix/var/nix/profiles/default/bin:${PATH}"

# Crear usuario www-data
RUN if ! getent group www-data >/dev/null; then groupadd -g 33 www-data; fi && \
    if ! id -u www-data >/dev/null 2>&1; then useradd -u 33 -g www-data -s /bin/bash -m www-data; fi

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
