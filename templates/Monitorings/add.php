<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Monitoring $monitoring
 */
?>
<div class="row">
  <aside class="col-md-3">
    <div class="side-nav">
      <h4 class="heading"><?= __('Actions') ?></h4>
      <?= $this->Html->link(__('List Monitorings'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
    </div>
  </aside>
  <div class="col-md-9">
    <div class="monitorings form content">
      <?= $this->Form->create($monitoring, ['class' => 'form']) ?>
      <fieldset>
        <legend>Aggiungi Punto di Monitoraggio</legend>
        <?php
        echo $this->Form->control('title', ['class' => 'form-control']);
        echo $this->Form->control('monitoring_date', ['empty' => true, 'class' => 'form-control', 'type' => 'date']);
        echo $this->Form->control('office_id', ['class' => 'form-control']);
        echo $this->Form->control('measure_id', ['class' => 'form-control']);
        echo $this->Form->control('values', ['class' => 'form-control']);
        ?>
      </fieldset>
      <?= $this->Form->button('Salva', ['class' => 'btn btn-primary mt-2']) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>