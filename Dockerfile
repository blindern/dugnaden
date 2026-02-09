FROM php:8.3-apache

ENV SIMPLESAMLPHP_VERSION=2.4.4

RUN set -eux; \
    # PHP extensions.
    docker-php-ext-install -j$(nproc) mysqli; \
    \
    # Install dependencies for SimpleSAMLphp.
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    \
    # Install Composer.
    curl -fSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    \
    # Install SimpleSAMLphp.
    mkdir /var/simplesamlphp; \
    cd /var/simplesamlphp; \
    curl -fSL "https://github.com/simplesamlphp/simplesamlphp/releases/download/v$SIMPLESAMLPHP_VERSION/simplesamlphp-$SIMPLESAMLPHP_VERSION-slim.tar.gz" \
      -o simplesamlphp.tar.gz; \
    tar --strip-components=1 -zxf simplesamlphp.tar.gz; \
    rm simplesamlphp.tar.gz; \
    composer install --no-dev --no-interaction; \
    \
    # Cleanup build deps.
    apt-get purge -y git unzip; \
    apt-get autoremove -y; \
    rm -rf /var/lib/apt/lists/*; \
    mkdir -p /var/cache/simplesamlphp; \
    chown www-data:www-data /var/cache/simplesamlphp

COPY simplesamlphp/config.override.php /var/simplesamlphp/config/
RUN cp /var/simplesamlphp/config/config.php.dist /var/simplesamlphp/config/config.php \
    && echo "require('config.override.php');" >>/var/simplesamlphp/config/config.php
COPY simplesamlphp/authsources.php /var/simplesamlphp/config/
COPY simplesamlphp/saml20-idp-remote.php /var/simplesamlphp/metadata/
COPY dugnaden /var/www/dugnaden/
COPY apache-site.conf /etc/apache2/sites-enabled/000-default.conf

EXPOSE 80
