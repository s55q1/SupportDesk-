FROM php:8.2-cli
RUN apt-get update && apt-get install -y libsqlite3-dev && docker-php-ext-install pdo_sqlite && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . /app
RUN mkdir -p /app/database /app/uploads && chmod -R 777 /app/database /app/uploads
EXPOSE 10000
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t ."]
