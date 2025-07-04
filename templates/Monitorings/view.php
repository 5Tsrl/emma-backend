<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Monitoring $monitoring
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Monitoring'), ['action' => 'edit', $monitoring->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Monitoring'), ['action' => 'delete', $monitoring->id], ['confirm' => __('Are you sure you want to delete # {0}?', $monitoring->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Monitorings'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Monitoring'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column-responsive column-80">
        <div class="monitorings view content">
            <h3><?= h($monitoring->title) ?></h3>
            <table>
                <tr>
                    <th><?= __('Title') ?></th>
                    <td><?= h($monitoring->title) ?></td>
                </tr>
                <tr>
                    <th><?= __('Indicators') ?></th>
                    <td><?= h($monitoring->indicators) ?></td>
                </tr>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= $this->Number->format($monitoring->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Monitoring Date') ?></th>
                    <td><?= h($monitoring->monitoring_date) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($monitoring->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($monitoring->modified) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
