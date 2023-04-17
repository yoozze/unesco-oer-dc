#! /bin/bash
set -e
set -o pipefail

MODE="$1"
ARGS="${@:2:$#}"

# Check if option passed is valid
OPTIONS_ARRAY=(
    "--build"
    "--start"
    "--stop"
    "--log"
    "--enter"
    "--sync-core"
    "--archive-dump"
    "--archive-restore"
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
    echo "bash run.sh --log [args]              | Display log"
    echo "bash run.sh --enter [args]            | Enter into container"
    echo "bash run.sh --sync-core [args]        | Synchronize core files on host"
    echo "bash run.sh --archive-dump            | Dump archive to archives directory"
    echo "bash run.sh --archive-restore [args]  | Restore archive from given file"
    exit 0
fi

source .env

COMPOSE_FILE="docker-compose.yml"
if [ $ENV = "development" ]; then
    COMPOSE_FILE="docker-compose.dev.yml"
fi

PREFIX="${ENV^^}"
echo "$PREFIX: $COMPOSE_FILE"

if [ $MODE = "--build" ]; then
    echo "$PREFIX: Building... $ARGS"
    docker-compose -f $COMPOSE_FILE build $ARGS
fi

if [ $MODE = "--start" ]; then
    echo "$PREFIX: Starting... $ARGS"
    docker-compose -f $COMPOSE_FILE up -d $ARGS
fi

if [ $MODE = "--stop" ]; then
    echo "$PREFIX: Stopping... $ARGS"
    docker-compose -f $COMPOSE_FILE down $ARGS
fi

if [ $MODE = "--log" ]; then
    echo "$PREFIX: Logging... $ARGS"
    docker-compose -f $COMPOSE_FILE logs -f $ARGS
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

ARCHIVE_PATH="./archive"
SETTINGS_FILE="/opt/drupal/web/sites/default/settings.php"

if [ $MODE = "--archive-dump" ]; then
    echo "$PREFIX: Archiving... $ARGS"
    docker exec ${PROJECT_NAME}_cms sh -c "
        cp $SETTINGS_FILE $SETTINGS_FILE.bak &&
        drush archive:dump --exclude-code-paths=web/sites/default/settings.php &&
        rm $SETTINGS_FILE.bak
    "
    mkdir -p $ARCHIVE_PATH
    docker cp ${PROJECT_NAME}_cms:/tmp/archive.tar.gz $ARCHIVE_PATH/"$(date +"%Y%m%dT%H%M%S").tar.gz"
    echo "$PREFIX: Archive saved to $ARCHIVE_PATH/$(date +"%Y%m%dT%H%M%S").tar.gz"
fi

if [ $MODE = "--archive-restore" ]; then
    echo "$PREFIX: Restoring archive... $ARGS"     
    docker cp $ARGS ${PROJECT_NAME}_cms:/tmp/archive.tar.gz
    docker exec ${PROJECT_NAME}_cms sh -c "
        drush archive:restore /tmp/archive.tar.gz --db &&
        drush archive:restore /tmp/archive.tar.gz --files --files-destination-relative-path web/sites/default/files
    "
    echo "$PREFIX: Archive restored"
fi
