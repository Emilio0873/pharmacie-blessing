#!/bin/bash
set -e

PORT="${PORT:-8080}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Ensure upload dirs exist (volume may be empty on first boot)
mkdir -p /var/www/html/uploads/products /var/www/html/uploads/clients /var/www/html/uploads/backups
chown -R www-data:www-data /var/www/html/uploads

exec apache2-foreground
