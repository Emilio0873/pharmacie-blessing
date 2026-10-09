FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app/

RUN mkdir -p /app/uploads/products /app/uploads/clients /app/uploads/backups \
    && chmod -R 775 /app/uploads

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
