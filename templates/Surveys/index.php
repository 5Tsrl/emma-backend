<?php
use Cake\Routing\Router;
$this->assign('title', 'Elenco Questionari');
?>

<?= $this->Html->link('Nuovo Questionario', ['action' => 'add'], ['class' => 'btn btn-primary']) ?>&nbsp;
<?= $this->Html->link('Importa da XLS', ['action' => 'import'], ['class' => 'btn btn-primary']) ?>&nbsp;
<!-- delete empty not used surveys -->
<a href="<?= Router::url(['action' => 'deleteSurveysEmpty']) ?>" class="btn btn-danger mb-4 float-right" onclick="return confirm('Vuoi davvero fare la pulizia?')">Delete empty not used surveys</a>
<table class="table table-striped">
  <thead>
    <tr>
      <th scope="col"><?= $this->Paginator->sort('id') ?></th>
      <th scope="col"><?= $this->Paginator->sort('name') ?></th>
      <th scope="col"><?= $this->Paginator->sort('company_id') ?></th>
      <th scope="col"><?= $this->Paginator->sort('version_tag') ?></th>
      <th scope="col"><?= $this->Paginator->sort('date') ?></th>
      <th scope="col"><?= $this->Paginator->sort('created') ?></th>
      <th scope="col"><?= $this->Paginator->sort('modified') ?></th>
      <th scope="col"><?= $this->Paginator->sort('user_id') ?></th>
      <th scope="col" class="actions"><?= __('Actions') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($surveys as $survey) : ?>
      <tr>
        <td><?= $this->Number->format($survey->id) ?></td>
        <td><?= h($survey->name) ?></td>
        <td><?= $survey->has('company') ? $this->Html->link($survey->company->name, ['controller' => 'Companies', 'action' => 'view', $survey->company->id]) : '' ?></td>
        <td><?= h($survey->version_tag) ?></td>
        <td><?= h($survey->date) ?></td>
        <td><?= h($survey->created) ?></td>
        <td><?= h($survey->modified) ?></td>
        <td><?= $survey->has('user') ? $this->Html->link($survey->user->id, ['controller' => 'Users', 'action' => 'view', $survey->user->id]) : '' ?></td>
        <td class="actions">
          <?= $this->Html->link(__('View'), ['action' => 'view', $survey->id], ['title' => __('View'), 'class' => 'btn btn-sm btn-secondary']) ?>
          <?= $this->Html->link('Risposte', ['controller' => 'Answers', 'action' => 'view', $survey->id], ['title' => 'Risposte', 'class' => 'btn btn-sm btn-secondary']) ?>
          <?= $this->Html->link(__('Edit'), ['action' => 'edit', $survey->id], ['title' => __('Edit'), 'class' => 'btn btn-sm btn-secondary']) ?>
          <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $survey->id], ['confirm' => __('Are you sure you want to delete # {0}?', $survey->id), 'title' => __('Delete'), 'class' => 'btn btn-sm btn-danger']) ?>
          <!-- delete empty participants -->
          <?= $this->Form->postLink(__('Delete empty participants'), ['action' => 'deleteEmptyParticipants', $survey->id], ['confirm' => __('Are you sure you want to delete empty participants?'), 'title' => __('Delete empty participants'), 'class' => 'btn btn-sm btn-danger']) ?>
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