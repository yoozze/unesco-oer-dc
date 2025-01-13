FROM drupal:10.4.1-php8.3-apache-bookworm

ARG ROOT
ARG ENV

# Copy project files from host
COPY $ROOT/drupal.sh ./
COPY $ROOT/package.json ./
COPY $ROOT/patches ./patches
COPY $ROOT/src/preprocess.php ./web/
COPY $ROOT/config/php.$ENV.ini /usr/local/etc/php/php.ini

# Install build dependencies
RUN apt-get update && apt-get install -y \
    nano \
    unzip \
    default-mysql-client \
    rsync \
    moreutils \
    jq \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Install modules
RUN touch /.dockerenv

RUN bash drupal.sh --config-composer

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
