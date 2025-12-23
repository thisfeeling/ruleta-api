#!/bin/bash
# setup-php-extensions.sh
set -e

# Configurar extensiones PHP adicionales si es necesario
PHP_INI_DIR=$(php -i | grep 'additional .ini files' | awk '{print $NF}')
mkdir -p "$PHP_INI_DIR"

echo "PHP extensions configured successfully"
