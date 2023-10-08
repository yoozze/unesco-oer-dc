FROM drupal:10.1.5-php8.2-apache-bookworm

ARG ROOT
ARG ENV

# Copy project files from host
COPY $ROOT/drupal.sh ./
COPY $ROOT/package.json ./
COPY $ROOT/patches ./patches
COPY $ROOT/src/.htaccess ./web/
COPY $ROOT/src/robots.txt ./web/
COPY $ROOT/config/php.$ENV.ini /usr/local/etc/php/php.ini

# Install build dependencies
RUN apt-get update && apt-get install -y \
    unzip \
    default-mysql-client \
    rsync \
    moreutils \
    jq \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Install modules
RUN touch /.dockerenv
RUN composer config repositories.1 composer https://asset-packagist.org && \
    composer config --json extra.installer-paths.web/libraries/\{\$name\} '["type:drupal-library", "type:bower-asset", "type:npm-asset"]' && \
    composer config --json extra.installer-types '["bower-asset", "npm-asset"]' && \
    composer config --no-plugins allow-plugins.oomphinc/composer-installers-extender true

RUN composer require \
    oomphinc/composer-installers-extender \
    npm-asset/select2 \
    drush/drush:^12.1

RUN bash drupal.sh --get-modules

# Apply patches
RUN bash patches/patch.sh

# Install APCu
RUN pecl install apcu

# Build assets
RUN jq '.workspaces=["web/modules/custom/*","web/themes/custom/*"]' package.json | sponge package.json
# RUN npm install && npm run build
