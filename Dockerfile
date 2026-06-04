FROM php:8.2-apache

# Installation de l'extension PDO MySQL nécessaire pour la connexion à MariaDB
RUN docker-php-ext-install pdo_mysql
