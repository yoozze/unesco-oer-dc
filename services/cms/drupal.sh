#! /bin/bash
set -e
set -o pipefail

MODE="$1"

# Check if option passed is valid
OPTIONS_ARRAY=( "--help" "--install" "--install-modules" "--install-site" "--install-user" )
VALID_OPTION="false"

if [ -z "$1" ]; then
    echo "Please specify an option!"
    IFS=$'\n'; echo "${OPTIONS_ARRAY[*]}"
    exit 1
fi

for k in "${OPTIONS_ARRAY[@]}"; do
    if [ $k = $1 ]; then
        VALID_OPTION="true"
    fi
done

if [ $VALID_OPTION = "false" ]; then
    echo "$1 is not a valid option! Valid options are: "
    IFS=$'\n'; echo "${OPTIONS_ARRAY[*]}"
    exit 1
fi

if [ $MODE = "--help" ]; then
    echo "bash drupal.sh --install              | Install everything"
    echo "bash drupal.sh --install-modules      | Install modules"
    echo "bash drupal.sh --install-site         | Install site"
    exit 0
fi

# STATUS=$(drush status 'DB name')
set +e
STATUS=$(drush config:get system.site name)
set -e
INSTALLED="false"

# if [[ "$STATUS" =~ "$DB_NAME" ]]; then
#     INSTALLED="true"
# fi

if [[ "$STATUS" =~ "$SITE_NAME" ]]; then
    INSTALLED="true"
fi

if [[ $MODE =~ "--install" ]] && [ $INSTALLED = "true" ]; then
    echo "Drupal already installed!"
    exit 0
fi

if [ $MODE = "--install" ]; then
    echo "Fixing files ownership..."
    chown www-data:www-data web/sites
    chown www-data:www-data web/sites/default
    chown www-data:www-data -R web/sites/default/files
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

    # Uninstall unused modeules

    # Install modules
    drush pm:enable --yes admin_toolbar
    drush pm:enable --yes admin_toolbar_tools
    drush pm:enable --yes backup_migrate
    drush pm:enable --yes coffee
    drush pm:enable --yes config_translation
    drush pm:enable --yes content_translation
    drush pm:enable --yes devel
    drush pm:enable --yes devel_generate
    drush pm:enable --yes language
    drush pm:enable --yes locale
    drush pm:enable --yes pathauto
    drush pm:enable --yes smtp
    drush pm:enable --yes drush_language
    # drush pm:enable --yes taxonomy_import
    # drush pm:enable --yes taxonomy_multidelete_terms

    # Set theme
    # drush theme:enable --yes unesco
    # drush config:set --yes system.theme default unesco

    # Set admin theme
    drush theme:enable --yes gin
    drush pm:enable --yes gin_toolbar
    drush config:set --yes system.theme admin gin

    # Set configuration
    drush config:set --yes smtp.settings smtp_on on

    # Add languages
    drush language-add fr
    drush language-add es
    drush language-add ru
    drush language-add ar
    drush language-add zh-hans
fi
