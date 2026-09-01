FROM php:8.3-cli

# Install ekstensi PDO MySQL (driver yang tadi hilang)
RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . .

# Pastikan folder uploads ada dan bisa ditulis
RUN mkdir -p uploads/destinations && chmod -R 777 uploads

EXPOSE 8000

# Railway menyediakan $PORT
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t ."]
