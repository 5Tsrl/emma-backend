<?php

use Cake\Core\Configure;

$azienda = '//' . env('HTTP_HOST') . '/'  . Configure::read('sitedir');
?>
<h1>Carica la guida utente</h1>
<hr>

<div class="form content">
  <?= $this->Form->create(null, ['type' => 'file']) ?>
  <?php  
  echo $this->Form->control('file', ['type' => 'file', 'class' => 'form-control']);
  ?>
  <br>
  <?= $this->Form->button(__('Submit')) ?>
  <?= $this->Form->end() ?>
</div>