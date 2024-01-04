#! /bin/bash
set -e
set -o pipefail

MODE="$1"

# Check if option passed is valid
OPTIONS_ARRAY=(
    "--help"
    "--config-composer"
    "--fix-permissions"
    "--get-modules"
    "--install"
    "--install-modules"
    "--install-site"
)
VALID_OPTION="false"

if [ -z "$1" ]; then
    echo "Please specify an option!"
    IFS=$'\n'; echo "${OPTIONS_ARRAY[*]}"
    exit 1
fi

for k in "${OPTIONS_ARRAY[@]}"; do
    if [ $k = $1 ]; then
        VALID_OPTION="true"
        break
    fi
done

if [ $VALID_OPTION = "false" ]; then
    echo "$1 is not a valid option! Valid options are: "
    IFS=$'\n'; echo "${OPTIONS_ARRAY[*]}"
    exit 1
fi

if [ $MODE = "--help" ]; then
    echo "bash drupal.sh --config-composer      | Configure composer"
    echo "bash drupal.sh --fix-permissions      | Fix filesystem permissions"
    echo "bash drupal.sh --get-modules          | Get modules"
    echo "bash drupal.sh --install              | Install everything"
    echo "bash drupal.sh --install-modules      | Install modules"
    echo "bash drupal.sh --install-site         | Install site"
    exit 0
fi

if [ $MODE = "--config-composer" ]; then
    echo "Configuring composer..."
    composer config repositories.1 composer https://asset-packagist.org
    composer config --json extra.installer-paths.web/libraries/\{\$name\} '["type:drupal-library", "type:bower-asset", "type:npm-asset"]'
    composer config --json extra.installer-types '["bower-asset", "npm-asset"]'
    composer config --no-plugins allow-plugins.oomphinc/composer-installers-extender true
fi

MODULES=(
    "admin_toolbar:^3.4.2"
    "backup_migrate:^5.0.3"
    "coder:^8.3.16"
    "coffee:^1.3"
    "devel:^5.1.1"
    "gin:^3.0@RC"
    "gin_toolbar:^1.0@RC"
    "pathauto:^1.11"
    "smtp:^1.2"
    "file_delete:^2.0"
    "drush_language:^1.0@RC"
    "easy_breadcrumb:^2.0"
    "language_switcher_extended:^1.1"
    "paragraphs:^1.15"
    "svg_image:^3.0"
    "views_bulk_operations:^4.2.3"
    "fancy_file_delete:^2.0"
    "file_replace:^1.3"
    "node_read_time:^1.13"
    "addtoany:^2.0.5"
    "views_ajax_history:^1.7"
    "select2:^1.15"
    "select2_multicheck:^1.0"
    "conditional_fields:^4.0@alpha"
    # "taxonomy_multidelete_terms:^1.4"
    # "realistic_dummy_content:^3.1"
    # "estimated_read_time:^1.0"
    # "taxonomy_import:^2.0.11"
    # "content_type_clone:^1.0"
)

if [ $MODE = "--get-modules" ]; then
    cmd="composer require"
    for module in "${MODULES[@]}"; do
        # name=${module%%:*}
        # version=${module#*:}
        cmd="$cmd drupal/$module"
    done
    eval $cmd
fi

# STATUS=$(drush status 'DB name')
# set +e
# STATUS=$(drush config:get system.site name)
# set -e
# INSTALLED="false"

# if [[ "$STATUS" =~ "$DB_NAME" ]]; then
#     INSTALLED="true"
# fi

# if [[ "$STATUS" =~ "$SITE_NAME" ]]; then
#     INSTALLED="true"
# fi

# if [[ $MODE =~ "--install" ]] && [ $INSTALLED = "true" ]; then
#     echo "Drupal already installed!"
#     exit 0
# fi

if [ $MODE = "--install" ] || [ $MODE = "--fix-permissions" ]; then
    echo "Fixing files ownership..."
    chown www-data:www-data web/sites
    chown www-data:www-data web/sites/default
    mkdir -p web/sites/default/files
    chown www-data:www-data -R web/sites/default/files
    # chmod 777 -R  web/sites/default/files
fi

if [ $MODE = "--install" ] || [ $MODE = "--install-site" ]; then
    echo "Installing site..."
    drush site:install \
        --site-name="$SITE_NAME" \
        --site-mail="$SITE_MAIL" \
        --account-name="$ACCOUNT_NAME" \
        --account-mail="$ACCOUNT_MAIL" \
        --account-pass="$ACCOUNT_NAME" \
        --db-url=mysql://$DB_USER:$DB_PASSWORD@db:$DB_PORT/$DB_NAME \
        --yes
fi

if [ $MODE = "--install" ] || [ $MODE = "--install-modules" ]; then
    echo "Installing modules..."

    # Install modules
    cmd="drush pm:install --yes"

    # - Core
    cmd="$cmd admin"
    cmd="$cmd config_translation"
    cmd="$cmd content_translation"
    cmd="$cmd language"
    cmd="$cmd locale"
    cmd="$cmd layout_builder"
    cmd="$cmd media_library"

    # - Contrib
    for module in "${MODULES[@]}"; do
        name=${module%%:*}

        if
            [ $name = "gin" ] ||
            [ $name = "gin_toolbar" ] ||
            [ $name = "coder" ];
        then
            continue
        fi

        cmd="$cmd $name"

        if [ $name = "admin_toolbar" ]; then
            cmd="$cmd admin_toolbar_tools"
        fi

        if [ $name = "devel" ]; then
            cmd="$cmd devel_generate"
        fi
    done

    # - Custom
    cmd="$cmd twig_extension"

    eval $cmd

    # Install themes
    drush theme:install --yes unesco_oer_dc gin
    drush pm:install --yes gin_toolbar

    # Configure
    drush config:set --yes system.theme default unesco_oer_dc
    drush config:set --yes system.theme admin gin
    drush config:set --yes smtp.settings smtp_on on

    # Add languages
    drush language-add fr
    # drush language-add es
    # drush language-add ru
    # drush language-add ar
    # drush language-add zh-hans

    # Uninstall unused core modeules
    # drush pm:uninstall --yes breakpoint

    # Uninstall on production
    if [ $ENV = "production" ]; then
        cmd="drush pm:uninstall --yes"
        cmd="$cmd coder"
        eval $cmd
    fi
fi
