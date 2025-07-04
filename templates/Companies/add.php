<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $company
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('List Companies'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="companies form content">
    <?= $this->Form->create($company) ?>
    <fieldset>
        <legend><?= __('Add Company') ?></legend>
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
