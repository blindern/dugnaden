# Dugnaden is using legacy features, so keeping on an old
# PHP version as of now. Note that this Docker version is
# no longer maintained.
FROM php:5.6-apache

ENV SIMPLESAMLPHP_VERSION=1.18.3
ENV SIMPLESAMLPHP_SHA256=c6cacf821ae689de6547092c5d0c854e787bfcda716096b1ecf39ad3b3882500
ENV COMPOSER_VERSION=1.9.1

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      wget \
    ; \
    rm -rf /var/lib/apt/lists/*; \
    # PHP extensions.
    docker-php-ext-install -j$(nproc) mysql pdo_mysql; \
    \
    # Set up composer.
    echo "$(wget -q -O- https://composer.github.io/installer.sig)  composer-setup.php" >composer-setup.php.sha384; \
    wget -O composer-setup.php https://getcomposer.org/installer; \
    sha384sum -c composer-setup.php.sha384; \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet; \
    rm composer-setup.php; \
    rm composer-setup.php.sha384; \
    \
    # Install SimpleSAMLphp.
    # See https://simplesamlphp.org/docs/stable/simplesamlphp-install
    mkdir /var/simplesamlphp; \
    cd /var/simplesamlphp; \
    curl -fSL "https://github.com/simplesamlphp/simplesamlphp/releases/download/v$SIMPLESAMLPHP_VERSION/simplesamlphp-$SIMPLESAMLPHP_VERSION.tar.gz" \
      -o simplesamlphp.tar.gz; \
    echo "$SIMPLESAMLPHP_SHA256 *simplesamlphp.tar.gz" | sha256sum -c -; \
    tar --strip-components=1 -zxf simplesamlphp.tar.gz; \
    rm simplesamlphp.tar.gz; \
    echo "require('config.override.php');" >>config/config.php

COPY simplesamlphp/config.override.php /var/simplesamlphp/config/
COPY simplesamlphp/authsources.php /var/simplesamlphp/config/
COPY simplesamlphp/saml20-idp-remote.php /var/simplesamlphp/metadata/
COPY dugnaden /var/www/dugnaden/
COPY composer.json /var/www
COPY apache-site.conf /etc/apache2/sites-enabled/000-default.conf

RUN set -eux; \
    cd /var/www; \
    composer dump-autoload

EXPOSE 80
