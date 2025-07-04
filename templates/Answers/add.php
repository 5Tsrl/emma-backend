<?php

/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $answer
 * @var \App\Model\Entity\Question[]|\Cake\Collection\CollectionInterface $questions
 * @var \App\Model\Entity\Survey[]|\Cake\Collection\CollectionInterface $surveys
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('List Answers'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="answers form content">
    <?= $this->Form->create($answer) ?>
    <fieldset>
        <legend><?= __('Add Answer') ?></legend>
        <?php
        echo $this->Form->control('question_id', ['options' => $questions, 'empty' => true]);
        echo $this->Form->control('survey_id', ['options' => $surveys, 'empty' => true]);
        echo $this->Form->control('user_id', ['options' => $users, 'empty' => true]);
        echo $this->Form->control('answer');
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>