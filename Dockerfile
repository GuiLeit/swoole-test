FROM php:8.2-cli-alpine 
# Or another suitable base image like php:8.2-fpm-alpine

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

# Update PECL channel and install Swoole extension
RUN pecl channel-update pecl.php.net \
    && pecl install openswoole \
    && docker-php-ext-enable openswoole

# Set your working directory inside the container
WORKDIR /app

# Copy your PHP application files into the container
COPY . /app

# Expose the port your Swoole server will listen on
EXPOSE 9501

# Command to run your Swoole server when the container starts
CMD ["php", "server.php"]