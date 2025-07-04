<?php

use Cake\Core\Configure;
use Cake\Routing\Router;

?>
<?php
//Carico le voci di menu extra portate da ogni plugin nel proprio element
$this->element('v-admin-extra-main-menu'); ?>
<div>
  <b-navbar toggleable="lg" variant="light">
    <div class="container">
      <b-navbar-brand href="<?= $this->Url->build('/') ?>">
        <?= $this->Html->image(Configure::read('VUE_APP_ICON'), [
          'height' => '80px'
        ]) ?>
      </b-navbar-brand>

      <b-navbar-toggle target="nav-collapse"></b-navbar-toggle>

      <b-collapse id="nav-collapse" is-nav>
        <!-- Right aligned nav items -->
        <b-navbar-nav class="ml-auto">
          <b-nav-item href="<?= Configure::read('FrontendUrl') ?>" target="preview">
            Vai alla Piattaforma
            <b-icon-globe></b-icon-globe>
          </b-nav-item>
        </b-navbar-nav>
      </b-collapse>
    </div>
  </b-navbar>
</div>