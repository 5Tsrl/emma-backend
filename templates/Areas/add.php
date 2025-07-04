<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Area $area
 * @var \Cake\Collection\CollectionInterface|string[] $users
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('List Areas'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column-responsive column-80">
        <div class="areas form content">
            <?= $this->Form->create($area) ?>
            <fieldset>
                <legend><?= __('Add Area') ?></legend>
                <?php
                    echo $this->Form->control('name',[ 'class' => 'form-control']);
                    echo $this->Form->control('city', ['class' => 'form-control']);
                    echo $this->Form->control('province', ['class' => 'form-control']);
                    echo $this->Form->control('polygon', ['class' => 'form-control']);
                    echo $this->Form->control('users._ids', ['options' => $users, 'class' => 'form-control']);
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
