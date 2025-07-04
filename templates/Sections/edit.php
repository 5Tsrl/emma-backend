<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Section $section
 */
?>
<div class="row">
  <aside class="col-md-4">
    <div class="side-nav">
      <h4 class="heading"><?= __('Actions') ?></h4>
      <?= $this->Form->postLink(
    __('Delete'),
    ['action' => 'delete', $section->id],
    ['confirm' => __('Are you sure you want to delete # {0}?', $section->id), 'class' => 'nav-link']
) ?>
      <?= $this->Html->link(__('List Sections'), ['action' => 'index'], ['class' => 'nav-link']) ?>
    </div>
  </aside>
  <div class="col-md-8">
    <div class="sections form content">
      <?= $this->Form->create($section) ?>
      <fieldset>
        <legend><?= __('Edit Section') ?></legend>
        <?= $this->Form->control('name', ['class' => 'form-control']); ?>
        <?= $this->Form->control('weight', ['class' => 'form-control', 'type' => 'number']); ?>
      </fieldset>
      <?= $this->Form->button(__('Submit')) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>