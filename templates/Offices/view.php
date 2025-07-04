<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Office $office
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('Edit Office'), ['action' => 'edit', $office->id], ['class' => 'nav-link']) ?></li>
<li><?= $this->Form->postLink(__('Delete Office'), ['action' => 'delete', $office->id], ['confirm' => __('Are you sure you want to delete # {0}?', $office->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Offices'), ['action' => 'index'], ['class' => 'nav-link']) ?> </li>
<li><?= $this->Html->link(__('New Office'), ['action' => 'add'], ['class' => 'nav-link']) ?> </li>
<li><?= $this->Html->link(__('List Companies'), ['controller' => 'Companies', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Company'), ['controller' => 'Companies', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="offices view large-9 medium-8 columns content">
    <h3><?= h($office->name) ?></h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th scope="row"><?= __('Name') ?></th>
                <td><?= h($office->name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Address') ?></th>
                <td><?= h($office->address) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Cap') ?></th>
                <td><?= h($office->cap) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('City') ?></th>
                <td><?= h($office->city) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Province') ?></th>
                <td><?= h($office->province) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Company') ?></th>
                <td><?= $office->has('company') ? $this->Html->link($office->company->name, ['controller' => 'Companies', 'action' => 'view', $office->company->id]) : '' ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Id') ?></th>
                <td><?= $this->Number->format($office->id) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Lat') ?></th>
                <td><?= $this->Number->format($office->lat) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Lon') ?></th>
                <td><?= $this->Number->format($office->lon) ?></td>
            </tr>
        </table>
    </div>
</div>
