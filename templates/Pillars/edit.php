<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Pillar $pillar
 */
?>
<div class="row">
  <aside class="col col-md-2">
    <div class="side-nav">
      <h4 class="heading">Azioni</h4>
      <?= $this->Form->postLink(
    __('Delete'),
    ['action' => 'delete', $pillar->id],
    ['confirm' => __('Are you sure you want to delete # {0}?', $pillar->id), 'class' => 'nav-link']
) ?>
      <?= $this->Html->link("Lista Pilastri", ['action' => 'index'], ['class' => 'nav-link']) ?>
    </div>
  </aside>
  <div class="col col-md-10">
    <div class="pillars form content">
      <?= $this->Form->create($pillar) ?>
      <fieldset>
        <legend>Modifica pilastro</legend>
        <?php
        echo $this->Form->control('name', ['class' => 'form-control']);
        echo $this->Form->control('description', ['class' => 'form-control']);
        ?>
      </fieldset>
      <br>
      <?= $this->Form->button(__('Submit'), ['class' => 'form-control btn btn-success']) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>