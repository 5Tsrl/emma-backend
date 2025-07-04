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
      <?= $this->Html->link(__('Edit Section'), ['action' => 'edit', $section->id], ['class' => 'nav-link']) ?>
      <?= $this->Form->postLink(__('Delete Section'), ['action' => 'delete', $section->id], ['confirm' => __('Are you sure you want to delete # {0}?', $section->id), 'class' => 'nav-link']) ?>
      <?= $this->Html->link(__('List Sections'), ['action' => 'index'], ['class' => 'nav-link']) ?>
      <?= $this->Html->link(__('New Section'), ['action' => 'add'], ['class' => 'nav-link']) ?>
    </div>
  </aside>
  <div class="col-md-8">
    <div class="sections view content">
      <h3><?= h($section->name) ?></h3>
      <table class="table table-striped">
        <tr>
          <th><?= __('Name') ?></th>
          <td><?= h($section->name) ?></td>

        </tr>
        <tr>
          <th><?= __('Id') ?></th>
          <td><?= $this->Number->format($section->id) ?></td>
        </tr>
        <tr>

          <th>Peso</th>
          <td><?= h($section->weight) ?></td>
        </tr>
      </table>

    </div>
  </div>
</div>