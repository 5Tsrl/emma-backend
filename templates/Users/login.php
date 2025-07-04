<div class="row">
  <div class="users form content col-md-4 offset-md-4">
    <?= $this->Form->create() ?>
    <fieldset>
      <legend>Login</legend>
      <?= $this->Form->control('email', ['class' => 'form-control']) ?>
      <?= $this->Form->control('password', ['class' => 'form-control']) ?>
    </fieldset>
    <?= $this->Form->button(__('Login')); ?>
    <?= $this->Form->end() ?>
  </div>
</div>