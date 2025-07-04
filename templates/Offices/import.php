<?php
$this->assign('title', 'Importa scuola');

$this->Breadcrumbs->add([
  ['title' => 'Home', 'url' => '/'],
  ['title' => 'Offices', 'url' => ['controller' => 'Offices', 'action' => 'index']],
]);
echo $this->Breadcrumbs->render(
  ['class' => 'breadcrumb'],
  ['separator' => ' &gt; ']
);
?>

<div class="file-import">
  <?= $this->Form->create(null, ['type' => 'file']); ?>
  <fieldset>
    <legend>Importa il file excel che contiene l'elenco delle sedi e il numero di studenti</legend>
    <small>per la individuare una scuola si utilizza il nome scuola e l'indirizzo</small>
    <p>Formato del file excel:<br>
      0: IDSEDE <br>
      1: SEDE<br>
      2: INDIRIZZO<br>
      3: COMUNE<br>
      4: CAP<br>
      5: PROVINCIA<br>
      6: Numero Lavoratori
    </p>
    <?= $this->Form->file('excelfile', ['class' => 'form-control']) ?>
  </fieldset>
  <?= $this->Form->button(__('Submit')) ?>
  <?= $this->Form->end() ?>
</div>