# API of the EMMA Mobility Management Platform

# Project Description
EMMA is a Electronic Mobility Management Application, developed by 5T, the Transport Authority of Turin, Italy.
It helps the Mobility Managers to develop and manage the Sustainable Mobility Plans of the companies and the schools.
It is a web application, composed by a backend (this repository - written in PHP using the CakePHP frameworkd) and a frontend (EMMA-Vue, written in Vue.js 2.0).
The backend is a REST API, which provides all the necessary functions to run the application.
The frontend is a Single Page Application, which consumes the REST API.

# Prerequisites
You need a running LAMPP server with PHP 7.2+, MySQL or MariaDB, Apache or Nginx

# Configurations
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
1.25.0701

# Authors
Settore Mobilità Sostenibile 5T (Customer) - mobilita.sostenibile@5t.torino.it
Massimo INFUNTI (Sw Architect and Main Developer) - massimoi@mobilitySquare.eu
Marco Toldo (Developer)
Sebastian Rojas (Developer)

# Copyrights (Mandatory)
© Copyright 5T – 2019-current

# License
SPDX-License-Identifier: EUPL-1.2
See the LICENSE.txt file for details

# Community site
https://github.com/5Tsrl/emma-backend.git
