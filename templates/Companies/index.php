<?php

/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface[]|\Cake\Collection\CollectionInterface $companies
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('New Company'), ['action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort('id') ?></th>
            <th scope="col"><?= $this->Paginator->sort('name') ?></th>
            <th scope="col"><?= $this->Paginator->sort('address') ?></th>
            <th scope="col"><?= $this->Paginator->sort('cap') ?></th>
            <th scope="col"><?= $this->Paginator->sort('city') ?></th>
            <th scope="col"><?= $this->Paginator->sort('province') ?></th>
            <th scope="col"><?= $this->Paginator->sort('country') ?></th>
            <th scope="col"><?= $this->Paginator->sort('num_employees') ?></th>
            <th scope="col"><?= $this->Paginator->sort('moma_id') ?></th>
            <th scope="col"><?= $this->Paginator->sort('company_type_id') ?></th>
            <th scope="col"><?= $this->Paginator->sort('ateco') ?></th>
            <th scope="col" class="actions"><?= __('Actions') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($companies as $company) : ?>
            <tr>
                <td><?= $this->Number->format($company->id) ?></td>
                <td><?= h($company->name) ?></td>
                <td><?= h($company->address) ?></td>
                <td><?= h($company->cap) ?></td>
                <td><?= h($company->city) ?></td>
                <td><?= h($company->province) ?></td>
                <td><?= h($company->country) ?></td>
                <td><?= $this->Number->format($company->num_employees) ?></td>
                <td><?= isset($company->user->username) ? h($company->user->username) : '' ?></td>
                <td><?= h($company->company_type_id) ?></td>
                <td><?= h($company->ateco) ?></td>
                <td class="actions">
                    <?= $this->Html->link(__('View'), ['action' => 'view', $company->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
                    <?= $this->Html->link(__('Edit'), ['action' => 'edit', $company->id], ['title' => __('Edit'), 'class' => 'btn btn-secondary']) ?>
                    <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $company->id], ['confirm' => __('Are you sure you want to delete # {0}?', $company->id), 'title' => __('Delete'), 'class' => 'btn btn-danger']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first('<< ' . __('First')) ?>
        <?= $this->Paginator->prev('< ' . __('Previous')) ?>
        <?= $this->Paginator->numbers(['before' => '', 'after' => '']) ?>
        <?= $this->Paginator->next(__('Next') . ' >') ?>
        <?= $this->Paginator->last(__('Last') . ' >>') ?>
    </ul>
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
</div>