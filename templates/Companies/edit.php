<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $company
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $company->id], ['confirm' => __('Are you sure you want to delete # {0}?', $company->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Companies'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="companies form content">
    <?= $this->Form->create($company) ?>
    <fieldset>
        <legend><?= __('Edit Company') ?></legend>
        <?php
            echo $this->Form->control('name');
            echo $this->Form->control('address');
            echo $this->Form->control('cap');
            echo $this->Form->control('city');
            echo $this->Form->control('province');
            echo $this->Form->control('country');
            echo $this->Form->control('num_employees');
            echo $this->Form->control('moma_id');
            echo $this->Form->control('company_type_id');
            echo $this->Form->control('ateco');
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
