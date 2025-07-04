<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Monitoring[]|\Cake\Collection\CollectionInterface $monitorings
 */
?>
<div class="monitorings index content">
  <?= $this->Html->link(__('New Monitoring'), ['action' => 'add'], ['class' => 'btn btn-primary float-right']) ?>
  <h3><?= __('Monitorings') ?></h3>

  <table class="table table-responsive">
    <thead>
      <tr>
        <th><?= $this->Paginator->sort('id') ?></th>
        <th><?= $this->Paginator->sort('title') ?></th>
        <th><?= $this->Paginator->sort('monitoring_date') ?></th>
        <th><?= $this->Paginator->sort('indicators') ?></th>
        <th><?= $this->Paginator->sort('created') ?></th>
        <th><?= $this->Paginator->sort('modified') ?></th>
        <th class="actions"><?= __('Actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($monitorings as $monitoring) : ?>
        <tr>
          <td><?= $this->Number->format($monitoring->id) ?></td>
          <td><?= h($monitoring->title) ?></td>
          <td><?= h($monitoring->monitoring_date) ?></td>
          <td><?= h($monitoring->indicators) ?></td>
          <td><?= h($monitoring->created) ?></td>
          <td><?= h($monitoring->modified) ?></td>
          <td class="actions">
            <?= $this->Html->link(__('View'), ['action' => 'view', $monitoring->id]) ?>
            <?= $this->Html->link(__('Edit'), ['action' => 'edit', $monitoring->id]) ?>
            <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $monitoring->id], ['confirm' => __('Are you sure you want to delete # {0}?', $monitoring->id)]) ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
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