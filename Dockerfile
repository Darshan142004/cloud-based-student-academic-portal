FROM php:8.2-apache

# Install MySQL extensions required by the application
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy the application into Apache's document root
COPY . /var/www/html/

# Set the working directory
WORKDIR /var/www/html

EXPOSE 80