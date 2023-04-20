#! /bin/bash
set -e
set -o pipefail

MODE="$1"

# Check if option passed is valid
OPTIONS_ARRAY=(
    "--help"
    "--install"
    "--install-modules"
    "--install-site"
    "--fix-permissions"
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
    echo "bash drupal.sh --fix-permissions      | Fix filesystem permissions"
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

    # Uninstall unused modeules

    # Install modules
    drush pm:install --yes admin_toolbar
    drush pm:install --yes admin_toolbar_tools
    drush pm:install --yes backup_migrate
    drush pm:install --yes coffee
    drush pm:install --yes config_translation
    drush pm:install --yes content_translation
    drush pm:install --yes devel
    drush pm:install --yes devel_generate
    drush pm:install --yes language
    drush pm:install --yes locale
    drush pm:install --yes pathauto
    drush pm:install --yes smtp
    drush pm:install --yes drush_language
    drush pm:install --yes file_delete
    drush pm:install --yes realistic_dummy_content
    drush pm:install --yes easy_breadcrumb
    drush pm:install --yes twig_extension
    drush pm:install --yes language_switcher_extended
    drush pm:install --yes layout_builder
    drush pm:install --yes paragraphs
    drush pm:install --yes svg_image
    # drush pm:install --yes taxonomy_import
    # drush pm:install --yes taxonomy_multidelete_terms

    # Set theme
    drush theme:install --yes unesco_oer_dc
    drush config:set --yes system.theme default unesco_oer_dc

    # Set admin theme
    drush theme:install --yes gin
    drush pm:install --yes gin_toolbar
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
