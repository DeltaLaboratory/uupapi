FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx && \
    mkdir -p /run/nginx && \
    echo 'display_errors = Off' > /usr/local/etc/php/conf.d/errors.ini

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY entrypoint.sh /entrypoint.sh

WORKDIR /var/www/html
COPY . .

RUN chmod +x sync.sh /entrypoint.sh && \
    mkdir -p cache fileinfo/metadata fileinfo/full && \
    chown -R www-data:www-data cache fileinfo

VOLUME ["/var/www/html/fileinfo", "/var/www/html/cache"]

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
