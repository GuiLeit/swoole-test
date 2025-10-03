FROM php:8.2-cli-alpine 

# Install necessary dependencies for Swoole
RUN apk add --no-cache \
    autoconf \
    gcc \
    g++ \
    make \
    linux-headers \
    libstdc++ \
    openssl-dev \
    curl-dev \
    postgresql-dev

# Update PECL channel and install Swoole and Redis extensions
RUN pecl channel-update pecl.php.net \
    && pecl install openswoole redis \
    && docker-php-ext-enable openswoole redis

# Set your working directory inside the container
WORKDIR /app

# Copy your PHP application files into the container
COPY . /app

# Expose the port your Swoole server will listen on
EXPOSE 9501

# Command to run both webhook.php and server.php in the background
CMD ["sh", "-c", "php webhook.php & php server.php"]
