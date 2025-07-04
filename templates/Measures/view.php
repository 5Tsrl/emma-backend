<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Measure $measure
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
<div class="row">
  <aside class="col-md-2">
    <div class="side-nav">
      <h4 class="heading"><?= __('Actions') ?></h4>
      <?= $this->Html->link(__('Edit Measure'), ['action' => 'edit', $measure->id], ['class' => 'nav-link']) ?>
      <?= $this->Form->postLink(__('Delete Measure'), ['action' => 'delete', $measure->id], ['confirm' => __('Are you sure you want to delete # {0}?', $measure->id), 'class' => 'nav-link']) ?>
      <?= $this->Html->link(__('List Measures'), ['action' => 'index'], ['class' => 'nav-link']) ?>
      <?= $this->Html->link(__('New Measure'), ['action' => 'add'], ['class' => 'nav-link']) ?>
    </div>
  </aside>
  <div class="col-md-8">
    <div class="measures view content">
      <h1><?= h($measure->name) ?></h1>
      <?= $this->Text->autoParagraph(h($measure->description)); ?>
      <table class="table table-striped">
        <tr>
          <th><?= __('Slug') ?></th>
          <td><?= h($measure->slug) ?></td>
        </tr>
        <tr>
          <th><?= __('Pillar') ?></th>
          <td><?= $measure->has('pillar') ? $this->Html->link($measure->pillar->name, ['controller' => 'Pillars', 'action' => 'view', $measure->pillar->id]) : '' ?></td>
        </tr>
        <tr>
          <th><?= __('Name') ?></th>
          <td><?= h($measure->name) ?></td>
        </tr>
        <tr>
          <th><?= __('Img') ?></th>
          <td><?= $this->Html->image($measure->img) ?></td>
        </tr>
        <tr>
          <th><?= __('Target') ?></th>
          <td><?= h($measure->target) ?></td>
        </tr>
        <tr>
          <th><?= __('Id') ?></th>
          <td><?= $this->Number->format($measure->id) ?></td>
        </tr>
        <tr>
          <th><?= __('Type') ?></th>
          <td><?= getMt($measure->type) ?></td>
        </tr>
        <tr>
          <th><?= __('Url Servizio') ?></th>
          <td><?= $measure->service_url ?></td>
        </tr>
      </table>

    </div>
  </div>
</div>