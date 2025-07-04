<?php

use Cake\Core\Configure;

$azienda = '//' . env('HTTP_HOST') . '/'  . Configure::read('sitedir');
$scuola = $azienda . "/modello-pscl-scuola.docx";
$azienda = $azienda . "/modello-pscl-azienda.docx";
?>
<h1>Carica il modello per la generazione del PSCL</h1>
<hr>
<p>
  Puoi scaricare il modello attuale per le
  <a href="<?= $scuola ?>">[scuole]</a> o le <a href="<?= $azienda ?>">[aziende]</a>.
</p>

<div class="offices form content">
  <?= $this->Form->create(null, ['type' => 'file']) ?>
  <?php
  echo $this->Form->control('target', ['class' => 'form-control', 'options' => ['scuola' => 'scuola', 'azienda' => 'azienda']]);
  echo $this->Form->control('file', ['type' => 'file', 'class' => 'form-control']);
  ?>
  <br>
  <?= $this->Form->button(__('Submit')) ?>
  <?= $this->Form->end() ?>
</div>