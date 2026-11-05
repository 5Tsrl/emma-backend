# API of the EMMA Mobility Management Platform

# Project Description
EMMA is a web-based application designed and developed by 5T on behalf of Regione Piemonte. It helps Mobility Managers draw up the "Sustainable Mobility Plan" (PSCL) aimed at reducing the negative impacts of employee commuting.

It is composed of a backend (this repository — written in PHP using the CakePHP framework) and a frontend (written in Vue.js 3). The backend is a REST API providing all necessary application features, while the frontend is a Single Page Application that consumes it.

The application consists of several modules, each hosted on its own repository:

- **emma-backend** (**this repository**): Application backend exposing REST APIs (developed with PHP/CakePHP).
- **[emma-frontend](https://github.com/5Tsrl/emma-frontend)**: Web frontend of the application (Single Page Application built with Vue.js).
- **[emma-frontend-bilancio](https://github.com/5Tsrl/emma-frontend-bilancio)**: Frontend sub-module for calculating the benefits achievable through measures planned by the Mobility Manager.
- **[emma-frontend-coworking](https://github.com/5Tsrl/emma-frontend-coworking)**: Frontend sub-module for identifying available coworking spaces within companies and public bodies.
- **[emma-frontend-parking](https://github.com/5Tsrl/emma-frontend-parking)**: Frontend sub-module for managing corporate parking facilities.

# Prerequisites
You need a running LAMPP server with PHP 8.2+, MySQL or MariaDB, Apache or Nginx

# Configurations
- The plugins in /plugins are git submodules: clone with ``git clone --recurse-submodules`` (or run ``git submodule update --init`` after cloning).
- ``SECURITY_SALT`` (a long random value, e.g. ``openssl rand -hex 32``) is mandatory: it signs the authentication tokens, and the application refuses to start without it. ``SECRET_FIELD_KEY`` is the key used by the SecretField plugin to encrypt sensitive fields. Both are read from the environment (see .env.sample).
- The software is multidomain and multiconfiguration (following the Drupal convention).
- Copy a settings_example.php in settings.php, and adapt it to your settings (in particular username and passwords of the db)
- The basic configuration is in the folder /config/sites/default/settings.php
- Configurations which are relevant for a specific domain can be found in the folder /config/sites/{domain}/settings.php
- The settings.php file is well documented and contains all the necessary information to configure the application

# Deployment
- Be sure you have is a running LAMPP environment (see cakephp4 requirements - https://book.cakephp.org/4/en/installation.html)
- Create a schema database, importing db/init.sql
- Put the name of the db in settings.php
- Be sure that the folders /webroot /tmp /logs have full write access from the web server (sudo chmod -R 777)
- Adapt the measures.php and pascal.php if needed
- Run ``composer update`` in the root folder in order to get all the dependencies
- Connect to your webserver and see the login page https://api.emma.test/
- You should see the login page.
- Be sure that the fronend url (the url from which the application is connected from the frontend is configured in the settings.php file)
- After testing, remember to set debug=false in your configure/sites/default/settings.php

## Docker Deployment
- In the root folder you find a Dockerfile which should help you to setup the environment.
- use docker-run.sh to run the docker container

# Versioning
1.25.0729

# Authors
- Regione Piemonte - Settore Mobilità  e Trasporti
- 5T - Settore Infomobilità e MaaS

# Copyrights
© Copyright Regione Piemonte

# License
SPDX-License-Identifier: EUPL-1.2

See the LICENSE.txt file for details
