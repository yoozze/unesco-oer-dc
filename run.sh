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

source .env

COMPOSE_FILE="docker-compose.yml"
if [ $ENV = "development" ]; then
    COMPOSE_FILE="docker-compose.dev.yml"
fi

PREFIX="${ENV^^}"
echo "$PREFIX: $COMPOSE_FILE"

if [ $MODE = "--build" ]; then
    echo "$PREFIX: Building... $ARGS"
    docker compose -f $COMPOSE_FILE build $ARGS

    if [ $ENV = "development" ]; then
        cd services/cms
        npm install
        npm run dev $ARGS
    fi
fi

if [ $MODE = "--start" ]; then
    echo "$PREFIX: Starting... $ARGS"
    docker compose -f $COMPOSE_FILE up -d $ARGS
fi

if [ $MODE = "--stop" ]; then
    echo "$PREFIX: Stopping... $ARGS"
    docker compose -f $COMPOSE_FILE down $ARGS
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
        mkdir -p /tmp/$ARCHIVE_NAME &&
        echo $PREFIX: - Database &&
        drush archive:dump --db --destination=/tmp/$ARCHIVE_NAME/db.tar.gz &&
        echo $PREFIX: - Files &&
        tar -zcf /tmp/$ARCHIVE_NAME/files.tar.gz web/sites/default/files &&
        tar -zcf /tmp/$ARCHIVE_NAME.tar.gz -C /tmp/$ARCHIVE_NAME . &&
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
        rm -rf /tmp/archive &&
        mkdir -p /tmp/archive &&
        tar -zxf /tmp/archive.tar.gz -C /tmp/archive &&
        echo $PREFIX: - Database &&
        drush archive:restore /tmp/archive/db.tar.gz --db &&
        echo $PREFIX: - Files &&
        tar -zxf /tmp/archive/files.tar.gz -C .
    "
    echo "$PREFIX: Archive restored"
fi

if [ $MODE = "--setup" ]; then
    echo "$PREFIX: Setting up... $ARGS"
    if [ $ENV = "production" ]; then
        docker exec ${PROJECT_NAME}_cms sh -c "
            npm install
            npm run build
        "
    fi
    docker exec ${PROJECT_NAME}_cms sh -c "
        bash drupal.sh --fix-permissions &&
        bash drupal.sh --install-modules &&
        drush updatedb --no-cache-clear &&
        drush cache:rebuild
    "
fi
