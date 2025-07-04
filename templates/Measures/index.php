<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Measure[]|\Cake\Collection\CollectionInterface $measures
 */
function getMt($mt)
{
  switch ($mt) {
    case 1:
      return 'misura';
    case 2:
      return 'servizio';
    default:
      return '---';
  }
}
?>
<div class="measures index content">
  <?= $this->Html->link(__('New Measure'), ['action' => 'add'], ['class' => 'btn btn-primary float-right']) ?>
  <h3><?= __('Measures') ?></h3>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th><?= $this->Paginator->sort('id') ?></th>
          <th><?= $this->Paginator->sort('slug') ?></th>
          <th><?= $this->Paginator->sort('pillar_id') ?></th>
          <th><?= $this->Paginator->sort('name') ?></th>
          <th><?= $this->Paginator->sort('img') ?></th>
          <th><?= $this->Paginator->sort('target') ?></th>
          <th><?= $this->Paginator->sort('type') ?></th>
          <th class="actions"><?= __('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($measures as $measure) : ?>
          <tr>
            <td><?= $this->Number->format($measure->id) ?></td>
            <td><?= h($measure->slug) ?></td>
            <td><?= $measure->has('pillar') ? $this->Html->link($measure->pillar->name, ['controller' => 'Pillars', 'action' => 'view', $measure->pillar->id]) : '' ?></td>
            <td><?= h($measure->name) ?></td>
            <td>
              <?php if (!empty($measure->img)) : ?>
                <img src="<?= $this->Url->image($measure->img) ?>?w=150&fit=crop" class="img-fluid">
              <?php endif ?>
            </td>
            <td><?= h($measure->target) ?></td>
            <td><?= getMT($measure->type) ?></td>
            <td class="actions">
              <?= $this->Html->link(__('View'), ['action' => 'view', $measure->id]) ?>
              <?= $this->Html->link(__('Edit'), ['action' => 'edit', $measure->id]) ?>
              <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $measure->id], ['confirm' => __('Are you sure you want to delete # {0}?', $measure->id)]) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="paginator">
    <ul class="pagination">
      <?= $this->Paginator->first('<< ' . __('first'),['tag' =>'li', 'class' => 'page-item', ' class' => 'page-link']) ?>
      <?= $this->Paginator->prev('< ' . __('previous'),['tag' =>'li', 'class' => 'page-item']) ?>
      <?= $this->Paginator->numbers(['tag' => 'li']) ?>
      <?= $this->Paginator->next(__('next') . ' >',['tag' => 'li']) ?>
      <?= $this->Paginator->last(__('last') . ' >>',['tag' => 'li']) ?>
    </ul>
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
  </div>
</div>