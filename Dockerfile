FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx && \
    mkdir -p /run/nginx && \
    echo 'display_errors = Off' > /usr/local/etc/php/conf.d/errors.ini

COPY nginx.conf /etc/nginx/http.d/default.conf

WORKDIR /var/www/html
COPY . .

RUN mkdir -p cache && chown www-data:www-data cache

EXPOSE 80

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
