#!/bin/sh

# Ensure directories exist and are writable
mkdir -p /var/www/html/cache /var/www/html/fileinfo/metadata /var/www/html/fileinfo/full
chown -R www-data:www-data /var/www/html/cache /var/www/html/fileinfo

# Start php-fpm
php-fpm -D

# Run initial sync in background (wait for nginx to be ready)
(sleep 3 && /var/www/html/sync.sh) &

# Schedule sync every 6 hours
(while true; do sleep 21600; /var/www/html/sync.sh; done) &

# Start nginx in foreground
exec nginx -g 'daemon off;'
