<div class="col-md-8">
    <div class="measures form content">
      <?= $this->Form->create($unionrollback, ['type' => 'file']) ?>
      <fieldset>
        <legend><?= __('Fai il rollback') ?></legend>
        <?php
        $options = $unionrollback->map(function ($value, $key) {
          return [
              'value' => $value->id,
              'text' => $value->name_union_questions,
          ];
      });
      if(is_null($union_id)){
        echo $this->Form->control('rollback_questions_id', ['options' => $options, 'empty' => false , 'value' => $union_id, 'class' => 'form-control']);
      }else{
        echo $this->Form->control('rollback_questions_id', ['options' => $options, 'empty' => false , 'value' => $union_id->id, 'class' => 'form-control']);
      }
        
        // echo $this->Form->control('destination_question_id', ['options' => $options, 'empty' => false , 'value' => $destination_question_id, 'class' => 'form-control']);
        ?>
      </fieldset>
      <?= $this->Form->button(__('Submit')) ?>
      <?= $this->Form->end() ?>
    </div>
  </div>