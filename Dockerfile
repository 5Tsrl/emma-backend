# FROM registry:5000/php7-ext
FROM php:8.2-apache

WORKDIR /var/www/html/

RUN apt-get update -y --allow-releaseinfo-change && \
    apt-get install -y --no-install-recommends wget libxml2-dev libicu-dev zlib1g-dev libpng-dev default-libmysqlclient-dev libzip-dev cron git && \
    docker-php-ext-install xml intl gd zip mysqli pdo_mysql && \
    apt-get install -y beanstalkd && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Node.js version 16.14
RUN curl -fsSL https://deb.nodesource.com/setup_16.x | bash - \
    && apt-get install -y nodejs=16.14.*

RUN apt-get update -y && \
    apt-get install -y unzip

RUN apt-get install -y openssh-client

RUN curl -sS https://getcomposer.org/installer |php

RUN mv composer.phar /usr/local/bin/composer

RUN mkdir vendor

RUN usermod -u 1000 www-data
RUN groupmod -g 1000 www-data
# Copy php.ini
COPY ./php.ini /usr/local/etc/php/

COPY . .


RUN composer update

# create cron task every 2 minutes
RUN echo "*/2 * * * * /var/www/html/bin/cake EmailQueue.sender -l 10 > /var/www/html/logs/cron.log 2>&1" >> /etc/cron.d/jobs
RUN echo "* * * * * /var/www/html/bin/cake beanstalk_worker > /var/www/html/logs/cron.log 2>&1" >> /etc/cron.d/jobs
RUN echo "* * * * * sleep 30; /var/www/html/bin/cake beanstalk_worker > /var/www/html/logs/cron.log 2>&1" >> /etc/cron.d/jobs
# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/jobs
# Apply cron job
RUN crontab /etc/cron.d/jobs
# Create the log file to be able to run tail
RUN touch /var/log/cron.log

# RUN mkdir tmp
RUN if [ ! -d "tmp" ]; then mkdir -p tmp/cache/models; fi
RUN if [ ! -d "logs" ]; then mkdir logs; fi
RUN touch tmp/out.xlsx
RUN chmod -R 777 tmp
RUN chmod -R 777 logs   


RUN chown -R www-data:www-data .
RUN a2enmod rewrite
# Set the working directory to webroot/js and run npm install
WORKDIR /var/www/html/webroot/js
RUN npm install
WORKDIR /var/www/html/webroot/
RUN wget -O download.zip https://cloud.mobilitysquare.eu/s/dZEPebD3a7YkEHe/download
RUN unzip download.zip -d .
RUN rm download.zip
RUN wget -O download.zip https://cloud.mobilitysquare.eu/s/4pkJbTQKBP8df3x/download
RUN unzip download.zip -d .
RUN rm download.zip
RUN wget -O download.zip https://cloud.mobilitysquare.eu/s/oKeeweMdNcF7PoA/download
RUN unzip download.zip -d .
RUN rm download.zip
WORKDIR /var/www/html
RUN chmod -R 777 webroot

COPY docker-start-services.sh /usr/local/bin/docker-start-services.sh

# Ensure the script has executable permissions
RUN chmod +x /usr/local/bin/docker-start-services.sh
# Use the script as the CMD
CMD ["/usr/local/bin/docker-start-services.sh"]