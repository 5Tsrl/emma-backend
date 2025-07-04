<div class="col-md-8">
    <div class="measures form content">
      <?= $this->Form->create($questions, ['type' => 'file']) ?>
      <fieldset>
        <legend><?= __('Unisci Domande') ?></legend>
        <?php
        $options = $questions->map(function ($value, $key) {
          return [
              'value' => $value->id,
              'text' => $value->id.'.'.$value->name,
          ];
      });
        echo $this->Form->control('remove_question_id', ['options' => $options, 'empty' => false , 'value' => $remove_question_id, 'class' => 'form-control']);
        echo $this->Form->control('destination_question_id', ['options' => $options, 'empty' => false , 'value' => $destination_question_id, 'class' => 'form-control']);
        ?>
      </fieldset>
      <?= $this->Form->button(__('Submit'),['confirm' => __('Are you sure you want to performe this union?'), 'title' => __('Delete'), 'class' => 'btn btn-danger']) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>