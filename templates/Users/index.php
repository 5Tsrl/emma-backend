<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users
 */
?>

<div class="monitorings index content">
  <?= $this->Html->link(__('New User'), ['action' => 'add'], ['class' => 'btn btn-primary float-right']) ?>
  <h3><?= __('Users') ?></h3>

  <table class="table table-responsive">
    <thead>
      <tr>
      <th><?= $this->Paginator->sort('id') ?></th> 
      <th><?= $this->Paginator->sort('email') ?></th>  
      <th><?= $this->Paginator->sort('first_name') ?></th>   
        <th><?= $this->Paginator->sort('last_name') ?></th>
        <th><?= $this->Paginator->sort('created') ?></th>
        <th><?= $this->Paginator->sort('modified') ?></th>
        <th class="actions"><?= __('Actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user) : ?>
        <tr>
          <td><?= h($user->id) ?></td>
          <td><?= h($user->email) ?></td>
          <td><?= h($user->first_name) ?></td>
          <td><?= h($user->last_name) ?></td>
          <td><?= h($user->created) ?></td>
          <td><?= h($user->modified) ?></td>
          <td class="actions">
            <?= $this->Html->link(__('View'), ['action' => 'view', $user->id]) ?>
            <?= $this->Html->link(__('Edit'), ['action' => 'edit', $user->id]) ?>
            <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $user->id], ['confirm' => __('Are you sure you want to delete # {0}?', $user->id)]) ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>