<?php
$this->assign('title', 'Dettagli Questionario ' . $survey->id);
?>
<a href="<?= $this->Url->build(['controller' => 'Answers', 'action' => 'view', $survey->id]) ?>" class="btn btn-primary">Vai alle Risposte</a>

<div class="surveys view large-9 medium-8 columns content">
  <h3><?= h($survey->name) ?></h3>
  <div class="table-responsive">
    <table class="table table-striped">
      <tr>
        <th scope="row"><?= __('Name') ?></th>
        <td><?= h($survey->name) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= __('Company') ?></th>
        <td><?= $survey->has('company') ? $this->Html->link($survey->company->name, ['controller' => 'Companies', 'action' => 'view', $survey->company->id]) : '' ?></td>
      </tr>
      <tr>
        <th scope="row"><?= __('Version Tag') ?></th>
        <td><?= h($survey->version_tag) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= __('User') ?></th>
        <td><?= $survey->has('user') ? $this->Html->link($survey->user->id, ['controller' => 'Users', 'action' => 'view', $survey->user->id]) : '' ?></td>
      </tr>
      <tr>
        <th scope="row"><?= __('Id') ?></th>
        <td><?= $this->Number->format($survey->id) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= __('Date') ?></th>
        <td><?= h($survey->date) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= __('Created') ?></th>
        <td><?= h($survey->created) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= __('Modified') ?></th>
        <td><?= h($survey->modified) ?></td>
      </tr>
    </table>
  </div>
  <div class="row">
    <div class="col">
      <h2><?= __('Description') ?></h2>
      <?= $this->Text->autoParagraph(h($survey->description)); ?>
    </div>
  </div>

  <div class="row">
    <h2>Domande in questo questionario</h2>
    <table class="table table-striped">
      <thead>
        <th>num</th>
        <th>nome</th>
        <th>tipo</th>
        <th>decrizione</th>
        <th>opzioni</th>
      </thead>
      <?php $i = 1 ?>
      <?php foreach ($survey->questions as $q) : ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= $q->name ?></td>
          <td>
            <?= $q->type ?>
            <?php if ($q->type != 'array') : ?>
              <a href="<?= $this->Url->build(['controller' => 'Questions', 'action' => 'convert_multi', $q->id]) ?>">
                Converti in domanda di tipo Array
              </a>
            <?php endif ?>
          </td>
          <td><?= $q->description ?></td>
          <td>
            <?= $this->Options->toCheckBox($q->options) ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>