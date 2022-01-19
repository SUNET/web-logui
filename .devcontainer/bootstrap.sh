#!/usr/bin/env bash

# Update packages
apt-get update

# Development
apt-get install -y git curl

# Apache
apt-get install -y apache2
a2enmod ssl
a2ensite default-ssl

# PHP
apt-get install -y php php-cli libapache2-mod-php php-curl php-sqlite3 php-intl php-zip

# Xdebug
apt-get install -y php-xdebug
echo "xdebug.remote_enable = 1" >> /etc/php/7.4/mods-available/xdebug.ini
echo "xdebug.remote_autostart = 1" >> /etc/php/7.4/mods-available/xdebug.ini
echo -n "xdebug.remote_connect_back = 1" >> /etc/php/7.4/mods-available/xdebug.ini

# Locales
apt-get install -y locales
locale-gen sv_SE.UTF-8
update-locale