#!/bin/sh
set -e

# Fix permissions for Laravel storage and bootstrap cache
# This is needed because Docker volume mounts from Windows override container permissions

echo "Setting storage permissions..."
mkdir -p storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Execute the main container command
exec "$@"