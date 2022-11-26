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
fi

if [ $MODE = "--start" ]; then
    echo "$PREFIX: Starting... $ARGS"
    docker compose -f $COMPOSE_FILE up -d $ARGS
fi

if [ $MODE = "--stop" ]; then
    echo "$PREFIX: Stopping... $ARGS"
    docker compose -f $COMPOSE_FILE down $ARGS
fi

if [ $MODE = "--log" ]; then
    echo "$PREFIX: Logging... $ARGS"
    docker compose -f $COMPOSE_FILE logs -f $ARGS
fi
