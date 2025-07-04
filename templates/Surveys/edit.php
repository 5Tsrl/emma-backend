<?php

/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $survey
 * @var \App\Model\Entity\Company[]|\Cake\Collection\CollectionInterface $companies
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users
 * @var \App\Model\Entity\Answer[]|\Cake\Collection\CollectionInterface $answers
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $survey->id], ['confirm' => __('Are you sure you want to delete # {0}?', $survey->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Surveys'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="surveys form content">
    <?= $this->Form->create($survey) ?>
    <fieldset>
        <legend><?= __('Edit Survey') ?></legend>
        <?php
        echo $this->Form->control('name');
        echo $this->Form->control('company_id', ['options' => $companies, 'empty' => true]);
        echo $this->Form->control('version_tag');
        echo $this->Form->control('description');
        echo $this->Form->control('date', ['empty' => true]);
        echo $this->Form->control('user_id', ['options' => $users, 'empty' => true]);
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>