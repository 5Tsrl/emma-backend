<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Pillar $pillar
 */
?>
<div class="row">
  <aside class="col-md-2">
    <div class="side-nav">
      <h4 class="heading">Azioni</h4>
      <?= $this->Html->link("Lista Pilastri", ['action' => 'index'], ['class' => 'nav-link']) ?>
    </div>
  </aside>
  <div class="col-md-10">
    <div class="pillars view content">
      <h1><?= $pillar->id ?>. <?= h($pillar->name) ?></h1>
      <div>
        <?= $this->Html->link("Modifica Pilastro", ['action' => 'edit', $pillar->id], ['class' => 'btn btn-primary btn-sm']) ?>
        <?= $this->Form->postLink("Delete Pilastro", ['action' => 'delete', $pillar->id], ['confirm' => __('Are you sure you want to delete # {0}?', $pillar->id), 'class' => ' btn btn-danger btn-sm']) ?>
      </div>
      <div class="text">
        <?= $this->Text->autoParagraph(h($pillar->description)); ?>
      </div>
      <div class="related">
        <h4><?= __('Miusure in questo pilastro') ?> <?= $this->Html->link("+ Nuova Misura", ['controller' => 'measures', 'action' => 'add', '?' => ['pillar_id' => $pillar->id]], ['class' => 'btn btn-success btn-sm']) ?>
        </h4>
        <?php if (!empty($pillar->measures)) : ?>
          <div class="table-responsive">
            <table class="table table-striped">
              <tr>
                <th><?= __('Id') ?></th>
                <th><?= __('Slug') ?></th>
                <th><?= __('Name') ?></th>
                <th width="30%"><?= __('Description') ?></th>
                <th width="30%"><?= __('Img') ?></th>
                <th><?= __('Target') ?></th>
                <th><?= __('Type') ?></th>
                <th class="actions"><?= __('Actions') ?></th>
              </tr>
              <?php foreach ($pillar->measures as $measures) : ?>
                <tr>
                  <td><?= h($measures->id) ?></td>
                  <td><?= h($measures->slug) ?></td>
                  <td><?= h($measures->name) ?></td>
                  <td width="30%"><?= h($measures->description) ?></td>
                  <td width="30%">
                    <?php if ($measures->img) : ?>
                      <img src="<?= $this->Url->image($measures->img) ?>?w=150&fit=crop" class="img-fluid">
                    <?php endif ?>
                  </td>
                  <td><?= h($measures->target) ?></td>
                  <td><?= h($measures->type) ?></td>

                  <td class="actions">
                    <?= $this->Html->link(__('View'), ['controller' => 'Measures', 'action' => 'view', $measures->id]) ?>
                    <?= $this->Html->link(__('Edit'), ['controller' => 'Measures', 'action' => 'edit', $measures->id]) ?>
                    <?= $this->Form->postLink(__('Delete'), ['controller' => 'Measures', 'action' => 'delete', $measures->id], ['confirm' => __('Are you sure you want to delete # {0}?', $measures->id)]) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>