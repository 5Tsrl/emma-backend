#!/bin/bash
# create log, tmp and /webroot/5T directories
mkdir -p ./tmp
mkdir -p ./logs
mkdir -p ./webroot/5T
# set permissions for the directories
chmod -R 777 ./tmp
chmod -R 777 ./logs 
chmod -R 777 ./webroot/5T
# run docker-compose up
docker-compose up -d