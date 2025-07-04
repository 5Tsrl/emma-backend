<?php

use Cake\Routing\Router;
?>
<a href="<?= Router::url(['action' => 'pulisci-doppie']) ?>" class="btn btn-danger mb-4 float-right" onclick="return confirm('Vuoi davvero fare la pulizia?')">Cancella domande doppie</a>

<table class="table table-striped">
  <thead>
    <tr>
      <th scope="col"><?= $this->Paginator->sort('id') ?></th>
      <th scope="col"><?= $this->Paginator->sort('name') ?></th>
      <th scope="col" width="20%"><?= $this->Paginator->sort('description') ?></th>
      <th scope="col" width="40%"><?= $this->Paginator->sort('options') ?></th>
      <th scope="col"><?= $this->Paginator->sort('Answers') ?></th>
      <th scope="col" class="actions"><?= __('Actions') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($questions as $question) : ?>
      <tr>
        <td>
          <?= $this->Number->format($question->id) ?>
        </td>
        <td><?= h($question->name) ?></td>
        <td width="20%"><?= h($question->description) ?>
        <?php if (!$question->surveys) : ?>
            <br><b style="color:red">Non usata da nessun questionario</b>
          <?php else : ?>
            <?php 
            $options = [];
            foreach ($question->surveys as $survey) {
              $options[] = [
                'value' => $survey->id,
                'text' => $survey->name.'-'.$survey->year,
              ];
            }
            echo $this->Form->control('Usata nel questionario:', ['options' => $options, 'empty' => false , 'class' => 'form-control'])?>
      <?php endif ?>
        </td>
      <td width="40%">
        <?= $this->Options->toCheckBox($question->options) ?>
      </td>
      <td>
      <?php if ($question->answers) : ?>
          <?= $this->Number->format($question->answers[0]->count) ?>
        <?php else : ?>
          <?= $this->Number->format(0) ?>
      <?php endif ?>
      </td>
      <td>
          <?= $this->Html->link(__('View'), ['action' => 'view', $question->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
          <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $question->id], ['confirm' => __('Are you sure you want to delete # {0}?', $question->id), 'title' => __('Delete'), 'class' => 'btn btn-danger']) ?>
          <?= $this->Html->link(__('Union'), ['action' => 'union', $question->id], ['title' => __('Union'), 'class' => 'btn btn-secondary']) ?>
          <?= $this->Html->link(__('Rollback'), ['action' => 'rollback', $question->id], ['title' => __('Rollback'), 'class' => 'btn btn-secondary']) ?>
          <?= $this->Form->postLink(__('Resetallsurveyquestions'), ['action' => 'correctQuestionsSurveys', $question->id], ['confirm' => __('Are you sure you want to reset # {0}?', $question->id), 'title' => __('Reset'), 'class' => 'btn btn-danger']) ?>
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