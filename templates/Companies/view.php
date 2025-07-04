<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $company
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('Edit Company'), ['action' => 'edit', $company->id], ['class' => 'nav-link']) ?></li>
<li><?= $this->Form->postLink(__('Delete Company'), ['action' => 'delete', $company->id], ['confirm' => __('Are you sure you want to delete # {0}?', $company->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Companies'), ['action' => 'index'], ['class' => 'nav-link']) ?> </li>
<li><?= $this->Html->link(__('New Company'), ['action' => 'add'], ['class' => 'nav-link']) ?> </li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="companies view large-9 medium-8 columns content">
    <h3><?= h($company->name) ?></h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th scope="row"><?= __('Name') ?></th>
                <td><?= h($company->name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Address') ?></th>
                <td><?= h($company->address) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Cap') ?></th>
                <td><?= h($company->cap) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('City') ?></th>
                <td><?= h($company->city) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Province') ?></th>
                <td><?= h($company->province) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Country') ?></th>
                <td><?= h($company->country) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Company Type Id') ?></th>
                <td><?= h($company->company_type_id) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Ateco') ?></th>
                <td><?= h($company->ateco) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Id') ?></th>
                <td><?= $this->Number->format($company->id) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Num Employees') ?></th>
                <td><?= $this->Number->format($company->num_employees) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Moma Id') ?></th>
                <td><?= $this->Number->format($company->moma_id) ?></td>
            </tr>
        </table>
    </div>
</div>
