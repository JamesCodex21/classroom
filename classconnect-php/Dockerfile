
FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite

WORKDIR /app

COPY . .

EXPOSE 10000

CMD php -S 0.0.0.0:${PORT:-10000} -t public
