FROM php:8.3-fpm

# Install PHP extensions
RUN docker-php-ext-install mysqli

# Set working directory
WORKDIR /usr/src/myapp

# Copy application files
COPY . /usr/src/myapp
