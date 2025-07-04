<?php
$this->assign('title', 'Importa Questionario');

$this->Breadcrumbs->add([
  ['title' => 'Home', 'url' => '/'],
  ['title' => 'Questionari', 'url' => ['controller' => 'Surveys', 'action' => 'index']],
]);
echo $this->Breadcrumbs->render(
    ['class' => 'breadcrumb'],
    ['separator' => ' &gt; ']
);
?>


<?= $this->Html->script('node_modules/axios/dist/axios.min.js', ['block' => true]) ?>
<?= $this->Html->script('vue/surveys/import.js', ['block' => true]) ?>

<div class="file-import">
  <?= $this->Form->create(null, ['type' => 'file']); ?>
  <fieldset>
    <legend>Scegli il file da importare in formato limesurvey</legend>
    <?= $this->Form->file('limesurvey', ['class' => 'form-control']) ?>
    <?= $this->Form->control('company_id', ['empty' => '--- Autocreate ---', 'class' => 'form-control']) ?>
    <?= $this->Form->control('description', ['class' => 'form-control']) ?>
    <?= $this->Form->control('date', ['type' => 'date', 'class' => 'form-control']) ?>
    <?= $this->Form->control('version_tag', ['class' => 'form-control']) ?>
  </fieldset>
  <?= $this->Form->button(__('Submit')) ?>
  <?= $this->Form->end() ?>
</div>

<hr>

<div id="app">
  <h2>Avanzamento</h2>
  <b-progress :value="adv" :max="max" show-progress animated></b-progress>
  {{message}}
</div>