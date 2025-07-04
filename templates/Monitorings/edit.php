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
            <?= $this->Form->postLink(
    __('Delete'),
    ['action' => 'delete', $monitoring->id],
    ['confirm' => __('Are you sure you want to delete # {0}?', $monitoring->id), 'class' => 'side-nav-item']
) ?>
            <?= $this->Html->link(__('List Monitorings'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column-responsive column-80">
        <div class="monitorings form content">
            <?= $this->Form->create($monitoring) ?>
            <fieldset>
                <legend><?= __('Edit Monitoring') ?></legend>
                <?php
                    echo $this->Form->control('title');
                    echo $this->Form->control('monitoring_date', ['empty' => true]);
                    echo $this->Form->control('indicators');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
