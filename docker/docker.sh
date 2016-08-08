#!/usr/bin/env bash

DOCKER_COMPOSE='./docker-compose.yml'

case "$1" in
    start)
        docker-compose -f $DOCKER_COMPOSE up -d;
        ;;
    stop)
        docker-compose -f $DOCKER_COMPOSE stop;
        ;;
    init)
        docker-compose -f $DOCKER_COMPOSE  up --force-recreate -d;
        ;;
    destroy)
        docker-compose -f $DOCKER_COMPOSE stop;
        docker-compose -f $DOCKER_COMPOSE rm;
        ;;
    update)
        docker-compose -f $DOCKER_COMPOSE stop;
        docker-compose -f $DOCKER_COMPOSE build;
        docker-compose -f $DOCKER_COMPOSE  up --force-recreate -d;
        ;;
        
    stopall)
        docker stop $(docker ps -a -q)
        ;;
    *)
        echo "Options to use: {start|stop|init|destroy|update}"
        exit 1
        ;;
esac
