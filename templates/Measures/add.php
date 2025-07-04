<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Measure $measure
 */
?>
<div class="row">
  <aside class="col-md-4">
    <div class="side-nav">
      <h4 class="heading"><?= __('Actions') ?></h4>
      <?= $this->Html->link(__('List Measures'), ['action' => 'index'], ['class' => 'nav-link']) ?>
    </div>
  </aside>
  <div class="col-md-8">
    <div class="measures form content">
      <?= $this->Form->create($measure) ?>
      <fieldset>
        <legend><?= __('Add Measure') ?></legend>
        <?php
        echo $this->Form->control('slug', ['class' => 'form-control']);
        echo $this->Form->control('pillar_id', ['options' => $pillars, 'empty' => true, 'class' => 'form-control']);
        echo $this->Form->control('name', ['class' => 'form-control']);
        echo $this->Form->control('description', ['class' => 'form-control']);
        echo $this->Form->control('img', ['class' => 'form-control']);
        echo $this->Form->control('target', ['class' => 'form-control', 'options' => ['scuola' => 'scuola', 'azienda' => 'azienda']]);
        echo $this->Form->control('type', ['class' => 'form-control', 'options' => [1 => 'misura', 2 => 'servizio']]);
        echo $this->Form->control('service_url', ['class' => 'form-control']);

        ?>
      </fieldset>
      <?= $this->Form->button(__('Submit')) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>