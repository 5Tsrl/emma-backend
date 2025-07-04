#!/bin/bash
# composer update
composer update
# get permission to write to the log file
chmod -R 777 /var/www/html/tmp
chmod -R 777 /var/www/html/logs 
# set environment variables also for cron
env >> /etc/environment
# start migration db
/var/www/html/bin/cake migrations migrate
# Start the services with the user
service beanstalkd start
service cron start
apache2-foreground