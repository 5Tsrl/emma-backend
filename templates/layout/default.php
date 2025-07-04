<?php

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */
//Invece di milligram uso questo fork che ha qualche elemento in più
//https://kidgodzilla.github.io/minimalist-css-framework/
use Cake\Core\Configure;
use Composer\Config;
?>
<!DOCTYPE html>
<html class="h-100">

<head>
  <?= $this->Html->charset() ?>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>
    <?= Configure::read('VUE_APP_TITLE_LONG') ?> | Backend |
    <?= $this->fetch('title') ?>
  </title>
  <?= $this->Html->meta('icon') ?>


  <link href="https://fonts.googleapis.com/css?family=Raleway:400,700" rel="stylesheet">

  <!-- Load required Bootstrap and BootstrapVue CSS -->
  <?= $this->Html->css('/js/node_modules/bootstrap/dist/css/bootstrap.min.css') ?>

  <?= $this->Html->css("/js/node_modules/bootstrap-vue/dist/bootstrap-vue.min.css") ?>

  <!-- Load Vue followed by BootstrapVue -->
  <?= $this->Html->script("/js/node_modules/vue/dist/vue.min.js") ?>
  <?= $this->Html->script("/js/node_modules/bootstrap-vue/dist/bootstrap-vue.min.js") ?>
  <?= $this->Html->script("/js/node_modules/bootstrap-vue/dist/bootstrap-vue-icons.min.js") ?>

  <?= $this->Html->css('style') ?>
  <?= $this->fetch('meta') ?>
  <?= $this->fetch('css') ?>

</head>

<body class="d-flex flex-column min-vh-100 h-100">
  <div id="app">

    <div v-cloak class="d-flex flex-column min-vh-100">

      <div class="v-cloak--inline">
        <!-- Parts that will be visible before compiled your HTML -->
        <div class="spinner"></div>
      </div>

      <div class="v-cloak--hidden">
        <!-- Parts that will be visible After compiled your HTML -->
        <?= $this->element('v-admin-header'); ?>
        <main role="main" class="container">
          <div class="mt-2"><?= $this->Flash->render() ?></div>
          <?= $this->fetch('content') ?>

        </main><!-- /.container -->
      </div>


    </div>
  </div>
  <?= $this->fetch('script') ?>
  <?= $this->element('v-autoload')  //Carica automaticamente lo script /theme/js/vue/{Controller}/{action}.js
  ?>
</body>

</html>