#! /bin/bash
set -e
set -o pipefail

# Always run from the project root so relative paths (e.g. .env) work under cron.
SCRIPT_DIR=$(cd "$(dirname "$(readlink -f "$0")")" && pwd)
cd "$SCRIPT_DIR"

# Cron uses a minimal PATH; ensure docker and common tools are available.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

MODE="$1"
ARGS="${@:2:$#}"

# Check if option passed is valid
OPTIONS_ARRAY=(
    "--build"
    "--start"
    "--stop"
    "--recreate"
    "--deploy"
    "--watch"
    "--log"
    "--enter"
    "--sync-core"
    "--sync-git"
    "--archive-dump"
    "--archive-restore"
    "--setup"
    "--help"
)
VALID_OPTION="false"

if [ -z "$1" ]; then
    echo "No option specified."
    echo "Available options:"
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
    echo "Invalid option: $1"
    echo "Available options:"
    IFS=$'\n'; echo "${OPTIONS_ARRAY[*]}"
    exit 1
fi

if [ $MODE = "--help" ]; then
    echo "bash run.sh --buld [args]             | Build services"
    echo "bash run.sh --start [args]            | Start services"
    echo "bash run.sh --stop [args]             | Stop services"
    echo "bash run.sh --recreate [args]         | Recreate services (stop, build, start and setup)"
    echo "bash run.sh --deploy [args]           | Deploy services (sync-git, build, start and setup)"
    echo "bash run.sh --watch [args]            | Watch files"
    echo "bash run.sh --log [args]              | Display log"
    echo "bash run.sh --enter [args]            | Enter into container"
    echo "bash run.sh --sync-core [args]        | Synchronize core drupal files on host (with drupal image)"
    echo "bash run.sh --sync-git [args]         | Synchronize source code on host (with Github image)"
    echo "bash run.sh --archive-dump            | Dump archive to archives directory"
    echo "bash run.sh --archive-restore [args]  | Restore archive from given file"
    echo "bash run.sh --setup [args]            | Setup services"
    exit 0
fi

if [ ! -f .env ]; then
    echo "Missing .env in $SCRIPT_DIR" >&2
    exit 1
fi

source .env

COMPOSE_FILE="docker-compose.yml"
if [ $ENV = "development" ]; then
    COMPOSE_FILE="docker-compose.dev.yml"
fi

PREFIX="${ENV^^}"
echo "$PREFIX: $COMPOSE_FILE"

if git -C "$SCRIPT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    export DEPLOYMENT_IDENTIFIER="$(git -C "$SCRIPT_DIR" rev-parse HEAD)"
else
    export DEPLOYMENT_IDENTIFIER="$(date -u +%Y%m%dT%H%M%SZ)"
fi

echo "$PREFIX: deployment identifier $DEPLOYMENT_IDENTIFIER"

# Drop the compiled Drupal container before Drush bootstraps. Constructor
# changes in custom modules otherwise reuse cache_container and TypeError.
invalidate_drupal_container_cache() {
    echo "$PREFIX: Invalidating Drupal container cache..."
    local tables
    tables=$(docker exec -e MYSQL_PWD="$DB_PASSWORD" "${PROJECT_NAME}_db" \
        mysql --user="$DB_USER" --database="$DB_NAME" -N -e "SHOW TABLES LIKE 'cache_container';") || {
        echo "$PREFIX: ERROR: could not reach database to truncate cache_container" >&2
        return 1
    }

    if [ -z "$tables" ]; then
        echo "$PREFIX: cache_container not present yet, skipping truncate"
        return 0
    fi

    docker exec -e MYSQL_PWD="$DB_PASSWORD" "${PROJECT_NAME}_db" \
        mysql --user="$DB_USER" --database="$DB_NAME" -e "TRUNCATE TABLE cache_container;"
    echo "$PREFIX: Truncated cache_container"
}

if [ $MODE = "--build" ]; then
    echo "$PREFIX: Building... $ARGS"
    docker compose -f $COMPOSE_FILE build $ARGS

    if [ $ENV = "development" ]; then
        cd services/cms
        npm install
        npm run dev $ARGS
    fi
fi

if [ $MODE = "--setup" ]; then
    echo "$PREFIX: Setting up... $ARGS"
    if [ $ENV = "production" ]; then
        docker exec ${PROJECT_NAME}_cms sh -c "
            npm install
            npm run build
        "
    fi
    PROD_SETUP=""
    if [ $ENV = "production" ]; then
        # devel is in config sync for local; uninstall on production after import.
        PROD_SETUP="drush pm:uninstall devel devel_generate -y 2>/dev/null || true &&"
    fi
    invalidate_drupal_container_cache
    docker exec ${PROJECT_NAME}_cms sh -c "
        set -e &&
        bash drupal.sh --fix-permissions &&
        drush config:import -y &&
        if ! drush config:status 2>&1 | grep -q 'No differences'; then
            echo 'ERROR: config sync incomplete after import (active DB still differs from sync dir).' >&2
            echo 'Re-run: drush config:import -y && drush updatedb -y' >&2
            drush config:status >&2 || true
            exit 1
        fi &&
        drush updatedb -y &&
        ${PROD_SETUP}
        drush locale:check &&
        drush locale:update -y &&
        drush cache:rebuild
    "
fi

if [ $MODE = "--start" ]; then
    echo "$PREFIX: Starting... $ARGS"
    docker compose -f $COMPOSE_FILE up -d $ARGS
fi

if [ $MODE = "--stop" ]; then
    echo "$PREFIX: Stopping... $ARGS"
    docker compose -f $COMPOSE_FILE down $ARGS
fi

if [ $MODE = "--recreate" ]; then
    ## run --stop, --build, --start and --setup
    bash run.sh --stop
    bash run.sh --build
    bash run.sh --start
    # wait for services to start
    sleep 10
    bash run.sh --setup
fi

if [ $MODE = "--deploy" ]; then
    ## run --sync-git, --build, --start and --setup (without stopping)
    bash run.sh --sync-git
    bash run.sh --build
    bash run.sh --start
    # wait for services to start
    sleep 10
    bash run.sh --setup
fi

if [ $MODE = "--watch" ]; then
    echo "$PREFIX: Watching... $ARGS"
    cd services/cms
    # npm install
    npm run watch $ARGS
fi

if [ $MODE = "--log" ]; then
    echo "$PREFIX: Logging... $ARGS"
    docker compose -f $COMPOSE_FILE logs -f $ARGS
fi

if [ $MODE = "--enter" ]; then
    echo "$PREFIX: Entering into container... $ARGS"

    if [ -z "$ARGS" ]; then
        docker exec -it ${PROJECT_NAME}_cms /bin/bash
    else
        docker exec -it ${PROJECT_NAME}_${ARGS} /bin/bash
    fi
fi

if [ $MODE = "--sync-core" ]; then
    echo "$PREFIX: Synchronyzing core files... $ARGS"
    SRC_PATH="./services/cms/src"

    if [ -z "$ARGS" ] || [ $ARGS = "core" ]; then
        echo " - core"
        sudo rm -rf $SRC_PATH/core
        sudo docker cp ${PROJECT_NAME}_cms:/opt/drupal/web/core $SRC_PATH
    fi
    
    if [ -z "$ARGS" ] || [ $ARGS = "modules" ]; then
        echo " - modules"
        sudo rm -rf $SRC_PATH/modules/contrib
        sudo docker cp ${PROJECT_NAME}_cms:/opt/drupal/web/modules/contrib $SRC_PATH/modules
    fi
    
    if [ -z "$ARGS" ] || [ $ARGS = "themes" ]; then
        echo " - themes"
        sudo rm -rf $SRC_PATH/themes/contrib
        sudo docker cp ${PROJECT_NAME}_cms:/opt/drupal/web/themes/contrib $SRC_PATH/themes
    fi
fi

if [ $MODE = "--sync-git" ]; then
    echo "$PREFIX: Synchronyzing source code from Github... $ARGS"
    sudo chown $USER:$USER services/cms/src/sites/default
    sudo chmod 755 services/cms/src/sites/default/
    git pull origin master
    # docker exec ${PROJECT_NAME}_cms sh -c "bash drupal.sh --fix-permissions"
    sudo chown www-data:www-data services/cms/src/sites/default
fi

ARCHIVE_PATH="./archive"
ARCHIVE_NAME="$(date +"%Y%m%dT%H%M%S")"

if [ $MODE = "--archive-dump" ]; then
    echo "$PREFIX: Archiving... $ARGS"
    docker exec ${PROJECT_NAME}_cms sh -c "
        set -e
        cd /opt/drupal
        mkdir -p /tmp/$ARCHIVE_NAME
        echo $PREFIX: - Database
        DRUSH_PHP_OPTIONS='-d memory_limit=2G' drush sql:dump --gzip --extra-dump=--no-tablespaces --result-file=/tmp/$ARCHIVE_NAME/db.sql
        echo $PREFIX: - Files
        tar -zcf /tmp/$ARCHIVE_NAME/files.tar.gz web/sites/default/files
        tar -zcf /tmp/$ARCHIVE_NAME.tar.gz -C /tmp/$ARCHIVE_NAME .
        rm -rf /tmp/$ARCHIVE_NAME
    "
    mkdir -p $ARCHIVE_PATH
    docker cp ${PROJECT_NAME}_cms:/tmp/$ARCHIVE_NAME.tar.gz "$ARCHIVE_PATH/$ARCHIVE_NAME.tar.gz"
    echo "$PREFIX: Archive saved to $ARCHIVE_PATH/$ARCHIVE_NAME.tar.gz"
fi

if [ $MODE = "--archive-restore" ]; then
    echo "$PREFIX: Restoring archive... $ARGS"
    docker cp $ARGS ${PROJECT_NAME}_cms:/tmp/archive.tar.gz
    docker exec ${PROJECT_NAME}_cms sh -c "
        set -e
        cd /opt/drupal
        rm -rf /tmp/archive
        mkdir -p /tmp/archive
        tar -zxf /tmp/archive.tar.gz -C /tmp/archive
        echo $PREFIX: - Database
        drush sql:drop -y
        if [ -f /tmp/archive/db.sql.gz ]; then
            gunzip -c /tmp/archive/db.sql.gz | drush sql:cli
        elif [ -f /tmp/archive/db.tar.gz ]; then
            tar -xzf /tmp/archive/db.tar.gz -C /tmp/archive database/database.sql
            drush sql:cli < /tmp/archive/database/database.sql
        else
            echo 'No database dump found in archive (expected db.sql.gz or db.tar.gz)' >&2
            exit 1
        fi
        echo $PREFIX: - Files
        tar -zxf /tmp/archive/files.tar.gz -C .
    "
    echo "$PREFIX: Archive restored"
fi
