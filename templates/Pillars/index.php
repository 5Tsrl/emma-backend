<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Pillar[]|\Cake\Collection\CollectionInterface $pillars
 */
?>
<div class="pillars index content">
  <?= $this->Html->link("Nuovo Pilastro", ['action' => 'add'], ['class' => 'btn btn-primary float-right']) ?>
  <h3>Pilastri</h3>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th><?= $this->Paginator->sort('id') ?></th>
          <th><?= $this->Paginator->sort('name') ?></th>
          <th class="actions"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pillars as $pillar) : ?>
          <tr>
            <td><?= $this->Number->format($pillar->id) ?></td>
            <td><?= h($pillar->name) ?></td>
            <td class="actions">
              <?= $this->Html->link("Elenco Misure", ['action' => 'view', $pillar->id]) ?>
              | <?= $this->Html->link(__('Edit'), ['action' => 'edit', $pillar->id]) ?>
              | <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $pillar->id], ['confirm' => __('Are you sure you want to delete # {0}?', $pillar->id)]) ?>
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