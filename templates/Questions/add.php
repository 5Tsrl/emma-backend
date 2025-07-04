<div class="questions form content">
  <?= $this->Form->create($question) ?>
  <fieldset>
    <legend><?= __('Add Question') ?></legend>
    <?php
    echo $this->Form->control('name');
    echo $this->Form->control('description');
    echo $this->Form->control('long_description');
    echo $this->Form->control('options');
    ?>
  </fieldset>
  <?= $this->Form->button(__('Submit')) ?>
  <?= $this->Form->end() ?>
</div>