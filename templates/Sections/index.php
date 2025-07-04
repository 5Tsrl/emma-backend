<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Section[]|\Cake\Collection\CollectionInterface $sections
 */
?>
<div class="sections index content">
  <?= $this->Html->link(__('New Section'), ['action' => 'add'], ['class' => 'btn btn-primary float-right']) ?>
  <h3><?= __('Sections') ?></h3>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th><?= $this->Paginator->sort('id') ?></th>
          <th><?= $this->Paginator->sort('name') ?></th>
          <th><?= $this->Paginator->sort('weight') ?></th>
          <th class="actions"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sections as $section) : ?>
          <tr>
            <td><?= $this->Number->format($section->id) ?></td>
            <td><?= h($section->name) ?></td>
            <td><?= h($section->weight) ?></td>
            <td class="actions">
              <?= $this->Html->link(__('View'), ['action' => 'view', $section->id]) ?>
              <?= $this->Html->link(__('Edit'), ['action' => 'edit', $section->id]) ?>
              <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $section->id], ['confirm' => __('Are you sure you want to delete # {0}?', $section->id)]) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="paginator">
    <ul class="pagination">
      <?= $this->Paginator->first('<< ' . __('first')) ?>
      <?= $this->Paginator->prev('< ' . __('previous')) ?>
      <?= $this->Paginator->numbers() ?>
      <?= $this->Paginator->next(__('next') . ' >') ?>
      <?= $this->Paginator->last(__('last') . ' >>') ?>
    </ul>
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
  </div>
</div>